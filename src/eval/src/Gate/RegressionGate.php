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
        if (!\in_array($this->baseline, $report->getVariants(), true)) {
            return [];
        }

        $results = [];
        foreach ($report->getVariants() as $variant) {
            if ($variant === $this->baseline) {
                continue;
            }

            $regressions = [];
            foreach ([null, ...$report->getLabels()] as $label) {
                $baseline = $report->getPassRate($this->baseline, $label);
                $candidate = $report->getPassRate($variant, $label);

                if (null === $baseline || null === $candidate) {
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
