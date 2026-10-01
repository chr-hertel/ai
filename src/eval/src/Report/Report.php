<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Report;

/**
 * The results of an eval suite run, with the metrics the gates and reporters work with.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Report
{
    /**
     * @var list<GateResult>
     */
    private array $gateResults = [];

    /**
     * @param list<CaseResult> $results
     */
    public function __construct(
        private readonly string $suite,
        private readonly array $results,
    ) {
    }

    public function getSuite(): string
    {
        return $this->suite;
    }

    /**
     * @return list<CaseResult>
     */
    public function getResults(?string $variant = null): array
    {
        if (null === $variant) {
            return $this->results;
        }

        return array_values(array_filter($this->results, static fn (CaseResult $result): bool => $result->getVariant() === $variant));
    }

    /**
     * @return list<string>
     */
    public function getVariants(): array
    {
        return array_values(array_unique(array_map(static fn (CaseResult $result): string => $result->getVariant(), $this->results)));
    }

    /**
     * @return list<string>
     */
    public function getLabels(): array
    {
        $labels = [];
        foreach ($this->results as $result) {
            $labels = [...$labels, ...$result->getCase()->getLabels()];
        }

        $labels = array_values(array_unique($labels));
        sort($labels);

        return $labels;
    }

    /**
     * @return list<string>
     */
    public function getEvaluators(): array
    {
        $evaluators = [];
        foreach ($this->results as $result) {
            foreach ($result->getScores() as $score) {
                $evaluators[$score->getEvaluator()] = true;
            }
        }

        return array_keys($evaluators);
    }

    public function getPassRate(string $variant, ?string $label = null): ?float
    {
        $results = $this->getResults($variant);
        if (null !== $label) {
            $results = array_values(array_filter($results, static fn (CaseResult $result): bool => $result->getCase()->hasLabel($label)));
        }

        if ([] === $results) {
            return null;
        }

        return \count(array_filter($results, static fn (CaseResult $result): bool => $result->isPassed())) / \count($results);
    }

    public function getMeanScore(string $variant, string $evaluator): ?float
    {
        $values = [];
        foreach ($this->getResults($variant) as $result) {
            if (null !== $score = $result->getScore($evaluator)) {
                $values[] = $score->getValue();
            }
        }

        return [] === $values ? null : array_sum($values) / \count($values);
    }

    public function getAverageCost(string $variant): ?float
    {
        $costs = array_values(array_filter(array_map(static fn (CaseResult $result): ?float => $result->getCost(), $this->getResults($variant)), static fn (?float $cost): bool => null !== $cost));

        return [] === $costs ? null : array_sum($costs) / \count($costs);
    }

    public function getAverageTokens(string $variant): ?float
    {
        $tokens = [];
        foreach ($this->getResults($variant) as $result) {
            if (null !== $total = $result->getRun()->getTokenUsage()?->getTotalTokens()) {
                $tokens[] = $total;
            }
        }

        return [] === $tokens ? null : array_sum($tokens) / \count($tokens);
    }

    /**
     * The latency below which 95 percent of the runs finished, in seconds.
     */
    public function getP95Latency(string $variant): float
    {
        $durations = array_map(static fn (CaseResult $result): float => $result->getRun()->getDuration(), $this->getResults($variant));
        if ([] === $durations) {
            return 0.0;
        }

        sort($durations);

        return $durations[(int) max(0, ceil(0.95 * \count($durations)) - 1)];
    }

    /**
     * Cases that passed in some repetitions and failed in others.
     *
     * @return list<string>
     */
    public function getFlakyCases(string $variant): array
    {
        $outcomes = [];
        foreach ($this->getResults($variant) as $result) {
            $outcomes[$result->getCase()->getId()][] = $result->isPassed();
        }

        $flaky = [];
        foreach ($outcomes as $case => $passed) {
            if (\count(array_unique($passed)) > 1) {
                $flaky[] = (string) $case;
            }
        }

        return $flaky;
    }

    /**
     * @return list<CaseResult>
     */
    public function getFailures(?string $variant = null): array
    {
        return array_values(array_filter($this->getResults($variant), static fn (CaseResult $result): bool => !$result->isPassed()));
    }

    public function addGateResults(GateResult ...$results): void
    {
        $this->gateResults = [...$this->gateResults, ...$results];
    }

    /**
     * @return list<GateResult>
     */
    public function getGateResults(): array
    {
        return $this->gateResults;
    }

    public function isPassed(): bool
    {
        foreach ($this->gateResults as $result) {
            if (!$result->isPassed()) {
                return false;
            }
        }

        return true;
    }
}
