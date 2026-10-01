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
 * Renders the summary table and the gates for a terminal.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextFormatter implements FormatterInterface
{
    public function format(Report $report): string
    {
        $variants = $report->getVariants();
        $labels = $report->getLabels();
        $cases = \count($report->getResults($variants[0] ?? null));

        $header = ['Variant', 'Pass rate', ...$labels, 'Avg tokens', 'Avg cost', 'p95 latency', 'Flaky'];
        $rows = [];
        foreach ($variants as $variant) {
            $row = [$variant, self::percent($report->getPassRate($variant))];
            foreach ($labels as $label) {
                $row[] = self::percent($report->getPassRate($variant, $label));
            }

            $tokens = $report->getAverageTokens($variant);
            $cost = $report->getAverageCost($variant);
            $row[] = null === $tokens ? '-' : number_format($tokens, 0);
            $row[] = null === $cost ? '-' : \sprintf('$%.4f', $cost);
            $row[] = \sprintf('%.2f s', $report->getP95Latency($variant));
            $row[] = (string) \count($report->getFlakyCases($variant));
            $rows[] = $row;
        }

        $widths = array_map(static fn (string $cell): int => mb_strlen($cell), $header);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], mb_strlen($cell));
            }
        }

        $line = static fn (array $cells): string => ' '.implode('   ', array_map(static fn (string $cell, int $width): string => $cell.str_repeat(' ', $width - mb_strlen($cell)), $cells, $widths));

        $output = [\sprintf('Suite "%s" - %d results per variant', $report->getSuite(), $cases), '', $line($header), ' '.str_repeat('-', array_sum($widths) + 3 * (\count($widths) - 1))];
        foreach ($rows as $row) {
            $output[] = $line($row);
        }

        if ([] !== $report->getGateResults()) {
            $output[] = '';
            $output[] = ' Gates';
            foreach ($report->getGateResults() as $gate) {
                $output[] = \sprintf('  %s %-10s %s', $gate->isPassed() ? 'OK  ' : 'FAIL', $gate->getVariant(), $gate->getMessage());
            }
        }

        $failures = $report->getFailures();
        if ([] !== $failures) {
            $output[] = '';
            $output[] = ' Failures';
            foreach ($failures as $failure) {
                $output[] = \sprintf('  %s / %s #%d: %s', $failure->getVariant(), $failure->getCase()->getId(), $failure->getRepetition(), implode(' ', $failure->getFailureReasons()));
            }
        }

        return implode("\n", $output)."\n";
    }

    private static function percent(?float $rate): string
    {
        return null === $rate ? '-' : \sprintf('%.1f %%', $rate * 100);
    }
}
