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
 * Limits the average cost of a case per variant.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CostPerCaseGate implements GateInterface
{
    public function __construct(
        private readonly float $max,
    ) {
    }

    public function check(Report $report): array
    {
        $results = [];
        foreach ($report->getVariants() as $variant) {
            $cost = $report->getAverageCost($variant);
            if (null === $cost) {
                $results[] = new GateResult('cost_per_case', $variant, false, 'cost per case not checked, the cost is unknown (no cost calculator, no "model" parameter or no price for the model)');

                continue;
            }

            $amount = $cost->getAmount();
            $results[] = new GateResult('cost_per_case', $variant, $amount <= $this->max, \sprintf('cost per case %.4f %s %s %.4f %s', $amount, $cost->getCurrency(), $amount <= $this->max ? '<=' : '>', $this->max, $cost->getCurrency()));
        }

        return $results;
    }
}
