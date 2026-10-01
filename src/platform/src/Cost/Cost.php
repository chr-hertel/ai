<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Cost;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Cost implements \Stringable
{
    public function __construct(
        private readonly float $amount,
        private readonly string $currency = 'USD',
    ) {
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function __toString(): string
    {
        return \sprintf('%.6F %s', $this->amount, $this->currency);
    }
}
