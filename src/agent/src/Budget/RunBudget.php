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

use Symfony\AI\Agent\Event\GuardrailTriggered;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Platform\Cost\Cost;
use Symfony\AI\Platform\Cost\CostCalculator;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Stops an agent run gracefully once it spent more tokens or money than allowed.
 *
 * Listens to {@see ToolCallsExecuted}: the usage of every tool calling round is summed up per run, and a run over
 * budget ends with the configured message as its result instead of asking the model again.
 *
 * To limit the runs of one agent only, enable "onlyProcessedRuns" and register it as the last input processor of that
 * agent, since processors before it may replace the run context.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RunBudget implements InputProcessorInterface
{
    /**
     * @var \WeakMap<RunContext, array{tokens: int, cost: Cost|null}>
     */
    private \WeakMap $spent;

    /**
     * @var \WeakMap<RunContext, true>
     */
    private \WeakMap $runs;

    public function __construct(
        private readonly ?int $maxTokens = null,
        private readonly ?float $maxCost = null,
        private readonly ?CostCalculator $costCalculator = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly string $message = 'I could not finish this request within the configured budget. Please narrow it down or ask a human for help.',
        private readonly bool $onlyProcessedRuns = false,
    ) {
        $this->spent = new \WeakMap();
        $this->runs = new \WeakMap();
    }

    public function __invoke(ToolCallsExecuted $event): void
    {
        $runContext = $event->getRunContext();
        $turn = $event->getTurn();
        $usage = $turn?->getMetadata()->get('token_usage');

        if (null === $runContext || null === $turn || !$usage instanceof TokenUsageInterface) {
            return;
        }

        if ($this->onlyProcessedRuns && !isset($this->runs[$runContext])) {
            return;
        }

        $spent = $this->spent[$runContext] ?? ['tokens' => 0, 'cost' => null];
        $spent['tokens'] += $usage->getTotalTokens() ?? 0;

        $model = $turn->getModel();
        $cost = $this->costCalculator?->calculate($model instanceof Model ? $model->getName() : $model, $usage);
        if (null !== $cost) {
            $spent['cost'] = null === $spent['cost'] ? $cost : $spent['cost']->add($cost);
        }

        $this->spent[$runContext] = $spent;

        $reason = $this->exceeded($spent);
        if (null === $reason) {
            return;
        }

        $event->setResult(new TextResult($this->message));

        $this->eventDispatcher?->dispatch(new GuardrailTriggered(
            'run_budget',
            GuardrailTriggered::ACTION_STOPPED,
            $reason,
            $runContext,
            ['tokens' => $spent['tokens'], 'cost' => $spent['cost']?->getAmount()],
        ));
    }

    public function processInput(Input $input): void
    {
        $this->runs[$input->getRunContext()] = true;
    }

    /**
     * @return array{tokens: int, cost: Cost|null}|null
     */
    public function getSpent(RunContext $runContext): ?array
    {
        return $this->spent[$runContext] ?? null;
    }

    /**
     * @param array{tokens: int, cost: Cost|null} $spent
     */
    private function exceeded(array $spent): ?string
    {
        if (null !== $this->maxTokens && $spent['tokens'] >= $this->maxTokens) {
            return \sprintf('The run used %d tokens, the budget is %d.', $spent['tokens'], $this->maxTokens);
        }

        if (null !== $this->maxCost && null !== $spent['cost'] && $spent['cost']->getAmount() >= $this->maxCost) {
            return \sprintf('The run cost %s, the budget is %.4F.', $spent['cost'], $this->maxCost);
        }

        return null;
    }
}
