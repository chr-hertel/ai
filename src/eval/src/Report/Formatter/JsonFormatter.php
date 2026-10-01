<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Report\Formatter;

use Symfony\AI\Eval\Report\Report;

/**
 * Renders the full report as JSON, for custom dashboards or for comparing runs over time.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class JsonFormatter implements FormatterInterface
{
    public function format(Report $report): string
    {
        $variants = [];
        foreach ($report->getVariants() as $variant) {
            $byLabel = [];
            foreach ($report->getLabels() as $label) {
                $byLabel[$label] = $report->getPassRate($variant, $label);
            }

            $scores = [];
            foreach ($report->getEvaluators() as $evaluator) {
                $scores[$evaluator] = $report->getMeanScore($variant, $evaluator);
            }

            $variants[$variant] = [
                'pass_rate' => $report->getPassRate($variant),
                'pass_rate_by_label' => $byLabel,
                'mean_scores' => $scores,
                'average_cost' => $report->getAverageCost($variant),
                'average_tokens' => $report->getAverageTokens($variant),
                'p95_latency' => $report->getP95Latency($variant),
                'flaky_cases' => $report->getFlakyCases($variant),
            ];
        }

        $results = [];
        foreach ($report->getResults() as $result) {
            $results[] = [
                'variant' => $result->getVariant(),
                'case' => $result->getCase()->getId(),
                'repetition' => $result->getRepetition(),
                'run_id' => $result->getRun()->getRunId(),
                'source_run' => $result->getCase()->getSourceRun(),
                'passed' => $result->isPassed(),
                'answer' => $result->getRun()->getAnswer(),
                'tools' => $result->getRun()->toolSequence(),
                'duration' => $result->getRun()->getDuration(),
                'cost' => $result->getCost(),
                'scores' => array_map(static fn ($score): array => [
                    'evaluator' => $score->getEvaluator(),
                    'value' => $score->getValue(),
                    'passed' => $score->isPassed(),
                    'explanation' => $score->getExplanation(),
                ], $result->getScores()),
            ];
        }

        $gates = array_map(static fn ($gate): array => [
            'gate' => $gate->getGate(),
            'variant' => $gate->getVariant(),
            'passed' => $gate->isPassed(),
            'message' => $gate->getMessage(),
        ], $report->getGateResults());

        return json_encode(['suite' => $report->getSuite(), 'passed' => $report->isPassed(), 'variants' => $variants, 'gates' => $gates, 'results' => $results], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
    }
}
