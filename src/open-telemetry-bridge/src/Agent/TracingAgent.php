<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\Agent;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Context\Context as AgentContext;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\OpenTelemetryBridge\ContentRedactorInterface;
use Symfony\AI\OpenTelemetryBridge\Guard;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\AppAttributes;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\GenAiAttributes;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\MessageSerializer;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\OpenInferenceAttributes;
use Symfony\AI\OpenTelemetryBridge\UserIdResolverInterface;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\BinaryResult;

/**
 * Produces an invoke_agent span covering the consumption of the lazy execution.
 *
 * The span is only active while the agent itself advances, never while the consumer handles an
 * update, so spans the consumer creates in between do not end up as children of the agent.
 *
 * The run is identified by the trace ID: unless the caller passes a run context with its own run ID, the agent gets
 * a run context carrying the trace ID, so feedback recorded for the run attaches to the trace. The final result
 * carries the trace ID as "trace_id" metadata, and the run context is recorded as "app.*" attributes on the span.
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

    public function call(string|MessageBag|UserMessage $input, AgentContext $agentContext = new AgentContext(), array $options = []): Execution
    {
        $span = Guard::run(fn () => $this->startSpan($input));

        if (null === $span) {
            return $this->agent->call($input, $agentContext, $options);
        }

        $agentContext = Guard::run(fn () => $this->seedRunContext($span, $agentContext)) ?? $agentContext;
        $context = $span->storeInContext(Context::getCurrent());

        try {
            $execution = Guard::inContext($context, fn () => $this->agent->call($input, $agentContext, $options));
        } catch (\Throwable $exception) {
            Guard::recordError($span, $exception);
            $span->end();

            throw $exception;
        }

        $cancellation = new Cancellation();
        $updates = fn (): \Generator => yield from $this->trace($cancellation->forward($execution), $span, $context);

        return new Execution(
            $updates,
            $execution->isStreamed(),
            $cancellation,
        );
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }

    /**
     * @return \Generator<int, UpdateInterface, mixed, void>
     */
    private function trace(Execution $execution, SpanInterface $span, ContextInterface $context): \Generator
    {
        try {
            $updates = $execution->getIterator();
            $valid = Guard::inContext($context, static fn (): bool => $updates->valid());

            while ($valid) {
                $update = $updates->current();

                if ($update instanceof Result) {
                    Guard::run(fn () => $this->recordResult($span, $update));
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
            Guard::recordError($span, $exception);

            throw $exception;
        } finally {
            // Also reached when the consumer abandons the execution, as the generator is destroyed
            Guard::run(static fn () => $span->end());
        }
    }

    private function startSpan(string|MessageBag|UserMessage $input): SpanInterface
    {
        $name = $this->agent->getName();
        $builder = $this->tracer->spanBuilder(GenAiAttributes::spanName(GenAiAttributes::OPERATION_INVOKE_AGENT, $name))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute(GenAiAttributes::OPERATION_NAME, GenAiAttributes::OPERATION_INVOKE_AGENT)
            ->setAttribute(GenAiAttributes::AGENT_NAME, $name);

        if ($input instanceof MessageBag) {
            $builder->setAttribute(GenAiAttributes::CONVERSATION_ID, $input->getId()->toRfc4122());
        }

        if (null !== $userId = $this->userIdResolver?->resolve()) {
            $builder->setAttribute(GenAiAttributes::USER_ID, $userId);
        }

        if ($this->captureContent) {
            $messages = match (true) {
                $input instanceof MessageBag => $input,
                $input instanceof UserMessage => new MessageBag($input),
                default => new MessageBag(new UserMessage(new Text($input))),
            };
            $builder->setAttribute(GenAiAttributes::INPUT_MESSAGES, $this->redact(MessageSerializer::inputMessages($messages)));

            // Workaround for the session view of Arize Phoenix, see OpenInferenceAttributes
            if (null !== $text = $this->latestUserText($messages)) {
                $builder->setAttribute(OpenInferenceAttributes::INPUT_VALUE, $this->redact($text));
            }
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

        // Workaround for the session view of Arize Phoenix, see OpenInferenceAttributes
        if (null !== $text = $message->asText()) {
            $span->setAttribute(OpenInferenceAttributes::OUTPUT_VALUE, $this->redact($text));
        }
    }

    /**
     * Gives the run the trace ID as run ID, unless the caller identified the run already.
     */
    private function seedRunContext(SpanInterface $span, AgentContext $agentContext): AgentContext
    {
        $spanContext = $span->getContext();
        if (!$spanContext->isValid()) {
            return $agentContext;
        }

        $runContext = $agentContext->get(RunContext::class);
        if (null === $runContext) {
            $runContext = new RunContext($spanContext->getTraceId());
        } elseif ('' === $runContext->getRunId()) {
            $runContext = $runContext->withRunId($spanContext->getTraceId());
        }

        $this->recordRunContext($span, $runContext);

        return $agentContext->replace($runContext);
    }

    /**
     * Records the final run context, which may have learned e.g. the prompt version during the run.
     */
    private function recordResult(SpanInterface $span, Result $update): void
    {
        $metadata = $update->getResult()->getMetadata();

        $runContext = $metadata->get('run_context');
        if ($runContext instanceof RunContext) {
            $this->recordRunContext($span, $runContext);
        }

        if ($span->getContext()->isValid()) {
            $metadata->add('trace_id', $span->getContext()->getTraceId());
        }
    }

    private function recordRunContext(SpanInterface $span, RunContext $runContext): void
    {
        $span->setAttribute(AppAttributes::RUN_ID, $runContext->getRunId());

        if (null !== $runContext->getRelease()) {
            $span->setAttribute(AppAttributes::RELEASE, $runContext->getRelease());
        }

        if (null !== $prompt = $runContext->getPrompt()) {
            $span->setAttribute(AppAttributes::PROMPT_NAME, $prompt->getName());
            $span->setAttribute(AppAttributes::PROMPT_VERSION, $prompt->getVersion());
        }

        if (null !== $runContext->getUserId()) {
            $span->setAttribute(GenAiAttributes::USER_ID, $runContext->getUserId());
        }

        foreach ($runContext->getAttributes() as $key => $value) {
            $span->setAttribute(AppAttributes::PREFIX.$key, $value);
        }
    }

    private function redact(string $content): string
    {
        return null === $this->redactor ? $content : $this->redactor->redact($content);
    }

    private function latestUserText(MessageBag $messages): ?string
    {
        foreach (array_reverse($messages->getMessages()) as $message) {
            if ($message instanceof UserMessage) {
                return $message->asText();
            }
        }

        return null;
    }
}
