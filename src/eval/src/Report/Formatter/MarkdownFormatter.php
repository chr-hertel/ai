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
 * Renders the report as Markdown, e.g. for a CI job summary or a pull request comment.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MarkdownFormatter implements FormatterInterface
{
    public function format(Report $report): string
    {
        $labels = $report->getLabels();
        $lines = [\sprintf('## Eval suite "%s"', $report->getSuite()), ''];

        $lines[] = '| Variant | Pass rate | '.implode(' | ', [...$labels, 'Avg cost', 'p95 latency', 'Flaky']).' |';
        $lines[] = '|'.str_repeat('---|', 5 + \count($labels));

        foreach ($report->getVariants() as $variant) {
            $cells = [$variant, self::percent($report->getPassRate($variant))];
            foreach ($labels as $label) {
                $cells[] = self::percent($report->getPassRate($variant, $label));
            }

            $cost = $report->getAverageCost($variant);
            $cells[] = null === $cost ? '-' : \sprintf('$%.4f', $cost);
            $cells[] = \sprintf('%.2f s', $report->getP95Latency($variant));
            $cells[] = (string) \count($report->getFlakyCases($variant));
            $lines[] = '| '.implode(' | ', $cells).' |';
        }

        if ([] !== $report->getGateResults()) {
            $lines[] = '';
            $lines[] = '### Gates';
            $lines[] = '';
            foreach ($report->getGateResults() as $gate) {
                $lines[] = \sprintf('- %s **%s** %s', $gate->isPassed() ? '✅' : '❌', $gate->getVariant(), $gate->getMessage());
            }
        }

        $failures = $report->getFailures();
        if ([] !== $failures) {
            $lines[] = '';
            $lines[] = '### Failures';
            $lines[] = '';
            foreach ($failures as $failure) {
                $sourceRun = $failure->getCase()->getSourceRun();
                $lines[] = \sprintf(
                    '- `%s` / `%s` #%d%s: %s',
                    $failure->getVariant(),
                    $failure->getCase()->getId(),
                    $failure->getRepetition(),
                    null !== $sourceRun ? ' (source run `'.$sourceRun.'`)' : '',
                    implode(' ', $failure->getFailureReasons()),
                );
            }
        }

        return implode("\n", $lines)."\n";
    }

    private static function percent(?float $rate): string
    {
        return null === $rate ? '-' : \sprintf('%.1f %%', $rate * 100);
    }
}
