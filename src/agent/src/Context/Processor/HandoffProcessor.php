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
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Handoff\Decision;
use Symfony\AI\Agent\Handoff\Handoff;
use Symfony\AI\Agent\Handoff\History;
use Symfony\AI\Platform\Exception\UnexpectedResultTypeException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Asks the model which of the {@see Handoff} items in the context should handle the request, and delegates the
 * run to that agent. When none is picked, the agent answers itself.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HandoffProcessor implements ContextProcessorInterface
{
    /**
     * @param int $routingWindow the number of most recent conversation messages the routing decision is based on
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly int $routingWindow = 10,
    ) {
        if ($routingWindow < 1) {
            throw new InvalidArgumentException('The routing window must contain at least one message.');
        }
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

        if (null === $request->getMessageBag()->getUserMessage()) {
            return;
        }

        $decision = $this->decide($request->getModel(), $handoffs, $request->getMessageBag(), $context->getCancellation());
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
        $delegation = $target->call($handoff->getHistory()->apply($request->getMessageBag()), $targetContext, $this->targetOptions($request->getOptions()));

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

    /**
     * @param list<Handoff> $handoffs
     */
    private function decide(string $model, array $handoffs, MessageBag $conversation, ?Cancellation $cancellation): ?Decision
    {
        $names = array_map(static fn (Handoff $handoff): string => $handoff->getTo()->getName(), $handoffs);

        $messages = History::window($this->routingWindow)->apply($conversation)->withSystemMessage(Message::forSystem($this->buildInstruction($handoffs)));
        $deferredDecision = $this->platform->invoke($model, $messages, ['response_format' => $this->decisionFormat($names)]);
        $cancellation?->activate($deferredDecision->getRawResult());

        try {
            if ($cancellation?->isRequested()) {
                return null;
            }

            // reasoning models wrap the decision into a multi-part result next to their thinking
            $decision = $deferredDecision->asObject();
        } catch (UnexpectedResultTypeException) {
            // without the structured output listener the platform answers with the JSON as text
            $decision = $this->decodeText($deferredDecision);
        } finally {
            $cancellation?->deactivate();
        }

        if ($decision instanceof Decision) {
            return $decision;
        }

        if (!\is_array($decision) || !\is_string($decision['agentName'] ?? null) || !\is_string($decision['reasoning'] ?? null)) {
            return null;
        }

        return new Decision($decision['agentName'], $decision['reasoning']);
    }

    private function decodeText(DeferredResult $result): mixed
    {
        try {
            return json_decode($result->asText(), true, flags: \JSON_THROW_ON_ERROR);
        } catch (UnexpectedResultTypeException|\JsonException) {
            return null;
        }
    }

    /**
     * Restricts the selectable agent names to the applicable handoffs, an empty name answers without handoff.
     *
     * @param list<string> $names
     *
     * @return array<string, mixed>
     */
    private function decisionFormat(array $names): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'Decision',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'agentName' => [
                            'type' => 'string',
                            'enum' => [...$names, ''],
                            'description' => 'The name of the selected agent, or an empty string if none of them matches.',
                        ],
                        'reasoning' => [
                            'type' => 'string',
                            'description' => 'The reasoning behind the selection.',
                        ],
                    ],
                    'required' => ['agentName', 'reasoning'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
        ];
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
    private function buildInstruction(array $handoffs): string
    {
        $agents = implode(\PHP_EOL, array_map(
            static fn (Handoff $handoff): string => \sprintf('- %s: %s', $handoff->getTo()->getName(), $handoff->getDescription()),
            $handoffs,
        ));

        return <<<INSTRUCTION
            You are an agent orchestrator. Based on the conversation, select the specialized agent that should handle
            the latest request of the user.

            Available agents and their capabilities:
            {$agents}

            Select the name of the most appropriate agent, or an empty string if none of them matches the request.
            Explain your reasoning.
            INSTRUCTION;
    }
}
