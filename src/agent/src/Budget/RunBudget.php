<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Budget;

use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Agent\Event\AgentInvocationCompleted;
use Symfony\AI\Agent\Event\AgentInvocationFailed;
use Symfony\AI\Agent\Event\GuardrailTriggered;
use Symfony\AI\Agent\Event\ModelRequested;
use Symfony\AI\Agent\Event\ModelResponded;
use Symfony\AI\Platform\Cost\Cost;
use Symfony\AI\Platform\Cost\CostCalculator;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Stops an agent run gracefully once it spent more tokens or money than allowed.
 *
 * The usage of every model response is summed up per run, and the next model request of a run over budget is not
 * sent: the run ends with the configured message as its result instead. Register the public methods as listeners of
 * the events they take, or use {@see self::getSubscribedEvents()} with an event subscriber.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RunBudget
{
    /**
     * @var array<string, array{tokens: int, cost: Cost|null}>
     */
    private array $spent = [];

    public function __construct(
        private readonly ?int $maxTokens = null,
        private readonly ?float $maxCost = null,
        private readonly ?CostCalculator $costCalculator = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly string $message = 'I could not finish this request within the configured budget. Please narrow it down or ask a human for help.',
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ModelResponded::class => 'onModelResponded',
            ModelRequested::class => 'onModelRequested',
            AgentInvocationCompleted::class => 'onCompleted',
            AgentInvocationFailed::class => 'onFailed',
        ];
    }

    public function onModelResponded(ModelResponded $event): void
    {
        $request = $event->getRequest();
        $usage = $event->getResult()->getMetadata()->get('token_usage');

        if (null === $request || !$usage instanceof TokenUsageInterface) {
            return;
        }

        $runId = $this->runId($request);
        $spent = $this->spent[$runId] ?? ['tokens' => 0, 'cost' => null];
        $spent['tokens'] += $usage->getTotalTokens() ?? 0;

        $cost = $this->costCalculator?->calculate($request->getModel(), $usage);
        if (null !== $cost) {
            $spent['cost'] = null === $spent['cost'] ? $cost : $spent['cost']->add($cost);
        }

        $this->spent[$runId] = $spent;
    }

    public function onModelRequested(ModelRequested $event): void
    {
        $request = $event->getRequest();
        $runId = $this->runId($request);
        $reason = $this->exceeded($runId);

        if (null === $reason) {
            return;
        }

        $event->stop(new TextResult($this->message));

        $this->eventDispatcher?->dispatch(new GuardrailTriggered(
            'run_budget',
            GuardrailTriggered::ACTION_STOPPED,
            $reason,
            $request->getContext()->get(RunContext::class),
            ['tokens' => $this->spent[$runId]['tokens'], 'cost' => $this->spent[$runId]['cost']?->getAmount()],
        ));
    }

    public function onCompleted(AgentInvocationCompleted $event): void
    {
        $runContext = $event->getResult()->getContext()->get(RunContext::class);
        if (null !== $runContext) {
            unset($this->spent[$runContext->getRunId()]);
        }
    }

    public function onFailed(AgentInvocationFailed $event): void
    {
        unset($this->spent[$this->runId($event->getRequest())]);
    }

    /**
     * @return array{tokens: int, cost: Cost|null}|null
     */
    public function getSpent(string $runId): ?array
    {
        return $this->spent[$runId] ?? null;
    }

    private function exceeded(string $runId): ?string
    {
        if (!isset($this->spent[$runId])) {
            return null;
        }

        $spent = $this->spent[$runId];

        if (null !== $this->maxTokens && $spent['tokens'] >= $this->maxTokens) {
            return \sprintf('The run used %d tokens, the budget is %d.', $spent['tokens'], $this->maxTokens);
        }

        if (null !== $this->maxCost && null !== $spent['cost'] && $spent['cost']->getAmount() >= $this->maxCost) {
            return \sprintf('The run cost %s, the budget is %.4F.', $spent['cost'], $this->maxCost);
        }

        return null;
    }

    private function runId(AgentRequest $request): string
    {
        return $request->getContext()->get(RunContext::class)?->getRunId() ?? spl_object_hash($request);
    }
}
