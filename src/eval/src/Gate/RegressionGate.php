<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Gate;

use Symfony\AI\Eval\Report\GateResult;
use Symfony\AI\Eval\Report\Report;

/**
 * Fails a variant if its overall or any per-label pass rate dropped more than allowed against the baseline variant.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RegressionGate implements GateInterface
{
    /**
     * @param float $maxDrop e.g. 0.02 for two percentage points
     */
    public function __construct(
        private readonly string $baseline,
        private readonly float $maxDrop,
    ) {
    }

    public function check(Report $report): array
    {
        $variants = array_values(array_diff($report->getVariants(), [$this->baseline]));
        $baselineRun = \in_array($this->baseline, $report->getVariants(), true);

        if ([] === $variants) {
            return [new GateResult('regression', $this->baseline, false, \sprintf('not checked, no variant to compare with the baseline "%s" was run', $this->baseline))];
        }

        $results = [];
        foreach ($variants as $variant) {
            if (!$baselineRun) {
                $results[] = new GateResult('regression', $variant, false, \sprintf('not checked, the baseline "%s" was not run', $this->baseline));

                continue;
            }

            $regressions = [];
            foreach ([null, ...$report->getLabels()] as $label) {
                $baseline = $report->getPassRate($this->baseline, $label);
                $candidate = $report->getPassRate($variant, $label);

                if (null === $baseline && null === $candidate && null !== $label) {
                    continue;
                }

                if (null === $baseline || null === $candidate) {
                    $regressions[] = \sprintf('%s not comparable, no results for %s', $label ?? 'pass rate', null === $baseline ? $this->baseline : $variant);

                    continue;
                }

                $drop = $baseline - $candidate;
                if ($drop > $this->maxDrop + 1e-9) {
                    $regressions[] = \sprintf('%s dropped %.1f points vs %s', $label ?? 'pass rate', $drop * 100, $this->baseline);
                }
            }

            $results[] = new GateResult(
                'regression',
                $variant,
                [] === $regressions,
                [] === $regressions ? \sprintf('no drop above %.1f points vs %s', $this->maxDrop * 100, $this->baseline) : implode(', ', $regressions).\sprintf(' (max %.1f)', $this->maxDrop * 100),
            );
        }

        return $results;
    }
}
