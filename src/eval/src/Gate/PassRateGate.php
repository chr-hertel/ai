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
        $results = [];

        foreach ($report->getVariants() as $variant) {
            if (null !== $this->variants && !\in_array($variant, $this->variants, true)) {
                continue;
            }

            $rate = $report->getPassRate($variant, $this->label);
            if (null === $rate) {
                continue;
            }

            $results[] = new GateResult($name, $variant, $rate >= $this->min, \sprintf('%s %.1f %% %s %.0f %%', $name, $rate * 100, $rate >= $this->min ? '>=' : '<', $this->min * 100));
        }

        return $results;
    }
}
