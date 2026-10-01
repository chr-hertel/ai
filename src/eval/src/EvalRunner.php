<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval;

use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\Report\CaseResult;
use Symfony\AI\Eval\Report\Report;
use Symfony\AI\Eval\Toolbox\FixtureToolbox;
use Symfony\AI\Platform\Cost\CostCalculator;

/**
 * Runs every case of a suite for every variant, scores the runs and checks the gates.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EvalRunner
{
    public function __construct(
        private readonly ?CostCalculator $costCalculator = null,
        private readonly RunRecorder $recorder = new RunRecorder(),
    ) {
    }

    /**
     * @param list<string>|null                $variants only run these variants, all if null
     * @param (\Closure(CaseResult): void)|null $onResult called after every case, e.g. to report progress
     */
    public function run(Suite $suite, ?array $variants = null, ?\Closure $onResult = null): Report
    {
        $selected = $this->selectVariants($suite, $variants);
        $results = [];

        foreach ($selected as $variant) {
            foreach ($suite->getCases() as $case) {
                for ($repetition = 1; $repetition <= $suite->getRepetitions(); ++$repetition) {
                    $result = $this->runCase($suite, $variant, $case, $repetition);
                    $results[] = $result;

                    if (null !== $onResult) {
                        $onResult($result);
                    }
                }
            }
        }

        $report = new Report($suite->getName(), $results);

        foreach ($suite->getGates() as $gate) {
            $report->addGateResults(...$gate->check($report));
        }

        return $report;
    }

    private function runCase(Suite $suite, Variant $variant, EvalCase $case, int $repetition): CaseResult
    {
        $toolCatalog = $suite->getToolCatalog();
        $toolbox = null !== $toolCatalog ? new FixtureToolbox($toolCatalog, $case->getFixtures()) : null;
        $agent = $suite->createAgent($variant, $toolbox, $case);

        // an empty run ID is filled in by the agent, or by a tracing decorator with the trace ID
        $runContext = new RunContext('', attributes: [
            'eval.suite' => $suite->getName(),
            'eval.variant' => $variant->getName(),
            'eval.case' => $case->getId(),
        ]);

        $run = $this->recorder->run($agent, $case->getMessages(), new Context($runContext));

        $scores = [];
        if (!$run->isFailed()) {
            foreach ($suite->getEvaluators() as $evaluator) {
                $score = $evaluator->evaluate($case, $run);
                if (null !== $score) {
                    $scores[] = $score;
                }
            }
        }

        $usage = $run->getTokenUsage();
        $model = $variant->get('model');
        $cost = null !== $usage && \is_string($model) ? $this->costCalculator?->calculate($model, $usage) : null;

        return new CaseResult($variant->getName(), $case, $repetition, $run, $scores, $cost?->getAmount());
    }

    /**
     * @param list<string>|null $names
     *
     * @return list<Variant>
     */
    private function selectVariants(Suite $suite, ?array $names): array
    {
        if (null === $names || [] === $names) {
            return $suite->getVariants();
        }

        $variants = [];
        foreach ($suite->getVariants() as $variant) {
            if (\in_array($variant->getName(), $names, true)) {
                $variants[] = $variant;
            }
        }

        if (\count($variants) !== \count(array_unique($names))) {
            throw new InvalidArgumentException(\sprintf('Unknown variant in "%s", the suite "%s" has "%s".', implode('", "', $names), $suite->getName(), implode('", "', array_map(static fn (Variant $variant): string => $variant->getName(), $suite->getVariants()))));
        }

        return $variants;
    }
}
