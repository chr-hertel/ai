<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\AgentResult;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\Context\ResultAwareContextProcessorInterface;
use Symfony\AI\Agent\Event\AgentInvocationCompleted;
use Symfony\AI\Agent\Event\AgentInvocationStarted;
use Symfony\AI\Agent\Event\HandoffCompleted;
use Symfony\AI\Agent\Event\HandoffRequested;
use Symfony\AI\Agent\Event\ModelRequested;
use Symfony\AI\Agent\Event\ModelResponded;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Handoff\Decision;
use Symfony\AI\Agent\Handoff\HandoffResolver;
use Symfony\AI\Agent\Store\MessageStoreInterface;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Drives a single agent invocation, producing the generator of updates that an
 * {@see Execution} wraps.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class Runner
{
    /**
     * @param ContextProcessorInterface[] $contextProcessors
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly array $contextProcessors,
        private readonly ?ToolExecutorInterface $toolExecutor = null,
        private readonly ?HandoffResolver $handoffResolver = null,
        private readonly ?int $maxToolCalls = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?MessageStoreInterface $store = null,
    ) {
    }

    /**
     * @param non-empty-string     $model
     * @param array<string, mixed> $options
     *
     * @return \Generator<int, UpdateInterface, mixed, void>
     */
    public function run(AgentInterface $agent, string $model, MessageBag $messages, Context $context, array $options): \Generator
    {
        if (null !== $this->store) {
            $messages = $this->store->load()->merge($messages);
        }

        $request = new AgentRequest($model, $messages, $options, $context);
        $agentContext = new AgentContext($agent);

        $started = new AgentInvocationStarted($agent, $request);
        $this->eventDispatcher?->dispatch($started);
        if ($started->hasResult()) {
            yield new ResultUpdate($started->getResult());

            return;
        }

        foreach ($this->applicableProcessors($request->getContext()) as $processor) {
            $processor->process($request, $agentContext);
            yield from $agentContext->flushUpdates();
        }

        if (null !== $this->handoffResolver && [] !== $this->handoffResolver->getApplicableHandoffs()) {
            $delegated = yield from $this->routeHandoff($agent, $request);
            if ($delegated instanceof ResultInterface) {
                yield new ResultUpdate($delegated);

                return;
            }
        }

        $result = yield from $this->invokeModel($agent, $request, $agentContext);

        $agentResult = new AgentResult($request->getModel(), $result, $request->getMessageBag(), $request->getOptions(), $request->getContext());
        foreach ($this->applicableProcessors($request->getContext()) as $processor) {
            if ($processor instanceof ResultAwareContextProcessorInterface) {
                $processor->processResult($agentResult, $agentContext);
                yield from $agentContext->flushUpdates();
            }
        }

        $this->persist($request, $agentResult->getResult());

        $this->eventDispatcher?->dispatch(new AgentInvocationCompleted($agent, $agentResult));

        yield new ResultUpdate($agentResult->getResult());
    }

    private function persist(AgentRequest $request, ResultInterface $result): void
    {
        if (null === $this->store) {
            return;
        }

        $messages = $request->getMessageBag();
        if ($result instanceof TextResult) {
            $messages = $messages->with(Message::ofAssistant($result->getContent()));
        }

        $this->store->save($messages);
    }

    /**
     * @return \Generator<int, UpdateInterface, mixed, ResultInterface>
     */
    private function invokeModel(AgentInterface $agent, AgentRequest $request, AgentContext $agentContext): \Generator
    {
        $iterations = 0;

        while (true) {
            $this->eventDispatcher?->dispatch(new ModelRequested($agent, $request));
            yield new Progress('model_request', 'Invoking model.', $request->getModel());

            $result = $this->platform->invoke($request->getModel(), $request->getMessageBag(), $request->getOptions())->getResult();
            $this->eventDispatcher?->dispatch(new ModelResponded($agent, $result));

            $toolCallResult = $this->extractToolCallResult($result);
            if (null === $toolCallResult || null === $this->toolExecutor) {
                return $result;
            }

            if (null !== $this->maxToolCalls && ++$iterations > $this->maxToolCalls) {
                throw new MaxIterationsExceededException($this->maxToolCalls);
            }

            $toolMessages = yield from $this->toolExecutor->execute($toolCallResult, $agentContext);
            foreach ($toolMessages as $message) {
                $request->getMessageBag()->add($message);
            }
        }
    }

    /**
     * @return \Generator<int, UpdateInterface, mixed, ResultInterface|null>
     */
    private function routeHandoff(AgentInterface $agent, AgentRequest $request): \Generator
    {
        \assert($this->handoffResolver instanceof HandoffResolver);

        $userMessage = $request->getMessageBag()->withoutSystemMessage()->getUserMessage();
        if (null === $userMessage) {
            return null;
        }

        $prompt = $this->handoffResolver->buildPrompt($userMessage->asText() ?? '');
        $decision = $this->platform->invoke(
            $request->getModel(),
            new MessageBag(Message::ofUser($prompt)),
            ['response_format' => Decision::class],
        )->getResult()->getContent();

        if (!$decision instanceof Decision || !$decision->hasAgent()) {
            return null;
        }

        $handoff = $this->handoffResolver->findByName($decision->getAgentName());
        if (null === $handoff) {
            return null;
        }

        $event = new HandoffRequested($agent, $handoff->getTo(), $decision->getReasoning());
        $this->eventDispatcher?->dispatch($event);

        $target = $event->getTarget();
        if (null === $target) {
            return null;
        }

        yield new Progress('handoff', \sprintf('Delegating to agent "%s".', $target->getName()), $target->getName());

        $result = null;
        foreach ($target->run($userMessage, new Context(), $request->getOptions()) as $update) {
            if ($update instanceof ResultUpdate) {
                $result = $update->getResult();

                continue;
            }

            yield $update;
        }

        if ($result instanceof ResultInterface) {
            $this->eventDispatcher?->dispatch(new HandoffCompleted($agent, $target, $result));
        }

        return $result;
    }

    private function extractToolCallResult(ResultInterface $result): ?ToolCallResult
    {
        if ($result instanceof ToolCallResult) {
            return $result;
        }

        if ($result instanceof MultiPartResult) {
            foreach ($result->getContent() as $part) {
                if ($part instanceof ToolCallResult) {
                    return $part;
                }
            }
        }

        return null;
    }

    /**
     * @return ContextProcessorInterface[]
     */
    private function applicableProcessors(Context $context): array
    {
        $applicable = [];
        foreach ($this->contextProcessors as $processor) {
            $types = $processor::supportedTypes();
            if ([] === $types) {
                $applicable[] = $processor;

                continue;
            }

            foreach ($types as $type) {
                if ($context->has($type)) {
                    $applicable[] = $processor;

                    continue 2;
                }
            }
        }

        return $applicable;
    }
}
