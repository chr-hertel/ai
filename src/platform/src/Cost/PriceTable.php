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
 * Looks up the price of a model, also for a dated snapshot of a configured model name.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PriceTable
{
    /**
     * @param array<string, Price> $prices keyed by model name
     */
    public function __construct(
        private readonly array $prices,
        private readonly string $currency = 'USD',
    ) {
    }

    /**
     * @param array<string, array{input: float, output: float, cache_read?: float, cache_write?: float}> $prices
     */
    public static function fromArray(array $prices, string $currency = 'USD'): self
    {
        $table = [];
        foreach ($prices as $model => $price) {
            $table[$model] = new Price($price['input'], $price['output'], $price['cache_read'] ?? null, $price['cache_write'] ?? null);
        }

        return new self($table, $currency);
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function get(string $model): ?Price
    {
        if (isset($this->prices[$model])) {
            return $this->prices[$model];
        }

        // providers report the resolved snapshot, e.g. "claude-sonnet-4-5-20250929" for "claude-sonnet-4-5"
        $match = null;
        foreach ($this->prices as $name => $price) {
            if (str_starts_with($model, $name.'-') && (null === $match || \strlen($name) > \strlen($match))) {
                $match = $name;
            }
        }

        return null !== $match ? $this->prices[$match] : null;
    }
}
