<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Agent;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Telemetry\ContentRedactorInterface;
use Symfony\AI\Telemetry\Guard;
use Symfony\AI\Telemetry\SemanticConvention\AppAttributes;
use Symfony\AI\Telemetry\SemanticConvention\GenAiAttributes;
use Symfony\AI\Telemetry\SemanticConvention\MessageSerializer;
use Symfony\AI\Telemetry\UserIdResolverInterface;

/**
 * Produces an invoke_agent span covering the consumption of the lazy execution.
 *
 * The span is only active while the agent itself advances, never while the consumer handles an
 * update, so spans the consumer creates in between do not end up as children of the agent.
 *
 * The run context of the agent gets the trace ID, so feedback recorded for the run can be attached to the trace. The
 * final result carries it as "trace_id" metadata, and the run context is recorded as "app.*" attributes on the span.
 * Its user ID is recorded as "user.id" only with a user ID resolver, and only if the resolver knows no user.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TracingAgent implements AgentInterface
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly TracerInterface $tracer,
        private readonly bool $captureContent = false,
        private readonly ?UserIdResolverInterface $userIdResolver = null,
        private readonly ?ContentRedactorInterface $redactor = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function call(string|MessageBag|UserMessage $input, array $options = []): ExecutionInterface
    {
        $userId = Guard::run(fn () => $this->userIdResolver?->resolve());
        $span = Guard::run(fn () => $this->startSpan($input, $userId));

        if (null === $span) {
            return $this->agent->call($input, $options);
        }

        $recordUser = null !== $this->userIdResolver && null === $userId;
        $options = Guard::run(fn () => $this->seedRunContext($span, $options, $recordUser)) ?? $options;
        $context = $span->storeInContext(Context::getCurrent());

        try {
            $execution = Guard::inContext($context, fn () => $this->agent->call($input, $options));
        } catch (\Throwable $exception) {
            Guard::recordError($span, $exception, $this->redactor);
            $span->end();

            throw $exception;
        }

        $cancellation = new Cancellation();
        $closer = new SpanCloser($span);
        $updates = fn (): \Generator => yield from $this->trace($cancellation->forward($execution), $span, $closer, $context, $recordUser);

        return new Execution(
            $updates,
            $execution->isStreamed(),
            $cancellation,
            $execution->getId(),
        );
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }

    /**
     * @return \Generator<int, UpdateInterface, mixed, void>
     */
    private function trace(ExecutionInterface $execution, SpanInterface $span, SpanCloser $closer, ContextInterface $context, bool $recordUser): \Generator
    {
        try {
            $updates = (static fn (): \Generator => yield from $execution)();
            $valid = Guard::inContext($context, static fn (): bool => $updates->valid());

            while ($valid) {
                $update = $updates->current();

                if ($update instanceof Result) {
                    Guard::run(fn () => $this->recordResult($span, $update, $recordUser));
                }

                if ($update instanceof Result && $this->captureContent) {
                    Guard::run(fn () => $this->recordOutput($span, $update));
                }

                yield $update;

                $valid = Guard::inContext($context, static function () use ($updates): bool {
                    $updates->next();

                    return $updates->valid();
                });
            }
        } catch (\Throwable $exception) {
            Guard::recordError($span, $exception, $this->redactor);

            throw $exception;
        } finally {
            // Also reached when the consumer abandons the execution, as the generator is destroyed
            $closer->end();
        }
    }

    private function startSpan(string|MessageBag|UserMessage $input, ?string $userId): SpanInterface
    {
        $name = $this->agent->getName();
        $builder = $this->tracer->spanBuilder(GenAiAttributes::spanName(GenAiAttributes::OPERATION_INVOKE_AGENT, $name))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute(GenAiAttributes::OPERATION_NAME, GenAiAttributes::OPERATION_INVOKE_AGENT)
            ->setAttribute(GenAiAttributes::AGENT_NAME, $name);

        if ($input instanceof MessageBag) {
            $builder->setAttribute(GenAiAttributes::CONVERSATION_ID, $input->getId()->toRfc4122());
        }

        if (null !== $userId) {
            $builder->setAttribute(GenAiAttributes::USER_ID, $userId);
        }

        if ($this->captureContent) {
            $messages = match (true) {
                $input instanceof MessageBag => $input,
                $input instanceof UserMessage => new MessageBag($input),
                default => new MessageBag(new UserMessage(new Text($input))),
            };
            $builder->setAttribute(GenAiAttributes::INPUT_MESSAGES, $this->redact(MessageSerializer::inputMessages($messages)));
        }

        return $builder->startSpan();
    }

    private function recordOutput(SpanInterface $span, Result $update): void
    {
        $result = $update->getResult();

        // Speech agents answer with audio, which is never inlined
        if ($result instanceof BinaryResult) {
            return;
        }

        $finishReason = $result->getMetadata()->get('finish_reason');
        $message = Message::ofAssistant($result);
        $span->setAttribute(GenAiAttributes::OUTPUT_MESSAGES, $this->redact(MessageSerializer::outputMessages($message, $finishReason instanceof FinishReason ? GenAiAttributes::finishReason($finishReason) : null)));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function seedRunContext(SpanInterface $span, array $options, bool $recordUser): array
    {
        $spanContext = $span->getContext();
        if (!$spanContext->isValid()) {
            return $options;
        }

        $runContext = $options['run_context'] ?? new RunContext();
        if (!$runContext instanceof RunContext) {
            return $options;
        }

        // an empty run ID is filled in by the agent when the run starts
        $runContext = $runContext->withTraceId($spanContext->getTraceId());

        $this->recordRunContext($span, $runContext, $recordUser);

        return ['run_context' => $runContext] + $options;
    }

    /**
     * Records the final run context, which may have learned e.g. the prompt version during the run.
     */
    private function recordResult(SpanInterface $span, Result $update, bool $recordUser): void
    {
        $metadata = $update->getResult()->getMetadata();

        $runContext = $metadata->get('run_context');
        if ($runContext instanceof RunContext) {
            $this->recordRunContext($span, $runContext, $recordUser);
        }

        if ($span->getContext()->isValid()) {
            $metadata->add('trace_id', $span->getContext()->getTraceId());
        }
    }

    private function recordRunContext(SpanInterface $span, RunContext $runContext, bool $recordUser): void
    {
        // free-form attributes first, so they cannot overwrite the fields below
        foreach ($runContext->getAttributes() as $key => $value) {
            $span->setAttribute(AppAttributes::PREFIX.$key, $value);
        }

        if ('' !== $runContext->getRunId()) {
            $span->setAttribute(AppAttributes::RUN_ID, $runContext->getRunId());
        }

        if (null !== $runContext->getRelease()) {
            $span->setAttribute(AppAttributes::RELEASE, $runContext->getRelease());
        }

        if (null !== $prompt = $runContext->getPrompt()) {
            $span->setAttribute(AppAttributes::PROMPT_NAME, $prompt->getName());
            $span->setAttribute(AppAttributes::PROMPT_VERSION, $prompt->getVersion());
        }

        if ($recordUser && null !== $runContext->getUserId()) {
            $span->setAttribute(GenAiAttributes::USER_ID, $runContext->getUserId());
        }
    }

    private function redact(string $content): string
    {
        return null === $this->redactor ? $content : $this->redactor->redact($content);
    }
}
