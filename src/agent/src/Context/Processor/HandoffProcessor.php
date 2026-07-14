<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context\Processor;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Agent\Event\HandoffCompleted;
use Symfony\AI\Agent\Event\HandoffRequested;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Handoff\Decision;
use Symfony\AI\Agent\Handoff\Handoff;
use Symfony\AI\Platform\Exception\UnexpectedResultTypeException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Asks the model which of the {@see Handoff} items in the context should handle the request, and delegates the
 * run to that agent. When none is picked, the agent answers itself.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HandoffProcessor implements ContextProcessorInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public static function supportedTypes(): array
    {
        return [Handoff::class];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
        $handoffs = array_values(array_filter(
            $request->getContext()->all(Handoff::class),
            static fn (Handoff $handoff): bool => $handoff->isApplicable(),
        ));

        if ([] === $handoffs) {
            return;
        }

        $userMessage = $request->getMessageBag()->withoutSystemMessage()->getUserMessage();
        if (null === $userMessage) {
            return;
        }

        $decision = $this->decide($request->getModel(), $this->buildPrompt($handoffs, $userMessage->asText() ?? ''), $context->getCancellation());
        if (!$decision instanceof Decision || !$decision->hasAgent()) {
            return;
        }

        $handoff = $this->findByName($handoffs, $decision->getAgentName());
        if (null === $handoff) {
            return;
        }

        $event = new HandoffRequested($context->getAgent(), $handoff->getTo(), $decision->getReasoning());
        $this->eventDispatcher?->dispatch($event);

        $target = $event->getTarget();
        if (null === $target) {
            return;
        }

        $context->reportProgress(Progress::STAGE_HANDOFF, \sprintf('Delegating to agent "%s".', $target->getName()), $target->getName());

        // the target brings its own instruction and handoffs, the delegating agent's must not leak into it
        $targetContext = $request->getContext()->without(Instruction::class)->without(Handoff::class);
        $delegation = $target->call($userMessage, $targetContext, $request->getOptions());

        $context->delegate($this->completing($delegation, $context->getAgent(), $target));
    }

    private function decide(string $model, string $prompt, ?Cancellation $cancellation): ?Decision
    {
        $deferredDecision = $this->platform->invoke($model, new MessageBag(Message::ofUser($prompt)), ['response_format' => Decision::class]);
        $cancellation?->activate($deferredDecision->getRawResult());

        try {
            if ($cancellation?->isRequested()) {
                return null;
            }

            // reasoning models wrap the decision into a multi-part result next to their thinking
            $decision = $deferredDecision->asObject();
        } catch (UnexpectedResultTypeException) {
            return null;
        } finally {
            $cancellation?->deactivate();
        }

        return $decision instanceof Decision ? $decision : null;
    }

    /**
     * Dispatches {@see HandoffCompleted} once the delegated execution produced its result.
     */
    private function completing(Execution $delegation, AgentInterface $source, AgentInterface $target): Execution
    {
        if (null === $this->eventDispatcher) {
            return $delegation;
        }

        $eventDispatcher = $this->eventDispatcher;
        $cancellation = new Cancellation();

        return new Execution(static function () use ($delegation, $source, $target, $eventDispatcher, $cancellation): \Generator {
            foreach ($cancellation->forward($delegation) as $update) {
                // the consumer may stop at the result, so the event has to be dispatched before yielding it
                if ($update instanceof ResultUpdate) {
                    $eventDispatcher->dispatch(new HandoffCompleted($source, $target, $update->getResult()));
                }

                yield $update;
            }
        }, $delegation->isStreamed(), $cancellation);
    }

    /**
     * @param list<Handoff> $handoffs
     */
    private function findByName(array $handoffs, string $agentName): ?Handoff
    {
        foreach ($handoffs as $handoff) {
            if ($handoff->getTo()->getName() === $agentName) {
                return $handoff;
            }
        }

        return null;
    }

    /**
     * @param list<Handoff> $handoffs
     */
    private function buildPrompt(array $handoffs, string $userQuestion): string
    {
        $descriptions = [];
        $names = [];
        foreach ($handoffs as $handoff) {
            $name = $handoff->getTo()->getName();
            $descriptions[] = \sprintf('- %s: %s', $name, $handoff->getDescription());
            $names[] = $name;
        }

        $agentList = implode("\n", $descriptions);
        $validAgents = implode('", "', $names);

        return <<<PROMPT
            You are an intelligent agent orchestrator. Based on the user's question, determine which specialized agent should handle the request.

            User question: "{$userQuestion}"

            Available agents and their capabilities:
            {$agentList}

            Analyze the user's question and select the most appropriate agent to handle this request.
            Return an empty string ("") for agentName if no specific agent matches the request criteria.

            Available agent names: "{$validAgents}"

            Provide your selection and explain your reasoning.
            PROMPT;
    }
}
