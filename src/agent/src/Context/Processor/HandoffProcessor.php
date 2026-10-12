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

use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Agent\Event\HandoffCompleted;
use Symfony\AI\Agent\Event\HandoffRequested;
use Symfony\AI\Agent\Execution\Cancellation;
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
        $delegation = $target->call($userMessage, $targetContext, $this->targetOptions($request->getOptions()));

        if (null !== $this->eventDispatcher) {
            $eventDispatcher = $this->eventDispatcher;
            $source = $context->getAgent();

            // result callbacks run before the result is yielded, so a consumer stopping at the result still sees the event
            $delegation->onResult(static function (ResultUpdate $update) use ($eventDispatcher, $source, $target): void {
                $eventDispatcher->dispatch(new HandoffCompleted($source, $target, $update->getResult()));
            });
        }

        $context->delegate($delegation, $target->getName());
    }

    /**
     * Drops the tool names restricting the delegating agent's toolbox, the target exposes its own toolbox.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function targetOptions(array $options): array
    {
        if (!isset($options['tools']) || !\is_array($options['tools']) || [] === $options['tools']) {
            return $options;
        }

        $options['tools'] = array_values(array_filter($options['tools'], static fn (mixed $tool): bool => !\is_string($tool)));

        // an empty tools option would allow no tool at all, while the list only restricted the delegating agent
        if ([] === $options['tools']) {
            unset($options['tools']);
        }

        return $options;
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
