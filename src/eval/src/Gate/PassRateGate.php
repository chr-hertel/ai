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
 * Requires a minimum pass rate per variant, optionally only for the cases with a label.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PassRateGate implements GateInterface
{
    /**
     * @param list<string>|null $variants the variants to check, all if null
     */
    public function __construct(
        private readonly float $min,
        private readonly ?string $label = null,
        private readonly ?array $variants = null,
    ) {
    }

    public function check(Report $report): array
    {
        $name = null === $this->label ? 'pass_rate' : 'pass_rate['.$this->label.']';
        $variants = null === $this->variants ? $report->getVariants() : array_values(array_intersect($this->variants, $report->getVariants()));
        if ([] === $variants) {
            return [new GateResult($name, implode(', ', $this->variants ?? []), false, \sprintf('%s not checked, none of the variants "%s" was run', $name, implode('", "', $this->variants ?? [])))];
        }

        $results = [];
        foreach ($variants as $variant) {
            $rate = $report->getPassRate($variant, $this->label);
            if (null === $rate) {
                $results[] = new GateResult($name, $variant, false, null === $this->label ? \sprintf('%s not checked, no results', $name) : \sprintf('%s not checked, no case with the label "%s"', $name, $this->label));

                continue;
            }

            $results[] = new GateResult($name, $variant, $rate >= $this->min, \sprintf('%s %.1f %% %s %.0f %%', $name, $rate * 100, $rate >= $this->min ? '>=' : '<', $this->min * 100));
        }

        return $results;
    }
}
