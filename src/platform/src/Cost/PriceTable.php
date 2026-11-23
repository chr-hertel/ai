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

use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;

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

    /**
     * Prices every model of the catalog that comes with a price, in USD per one million tokens.
     */
    public static function fromModelCatalog(ModelCatalogInterface $catalog): self
    {
        $prices = [];
        foreach ($catalog->getModels() as $model => $definition) {
            if (isset($definition['price'])) {
                $prices[$model] = $definition['price'];
            }
        }

        return self::fromArray($prices);
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Prefers an exact match of any given model over a dated snapshot, e.g. "gpt-4o-2024-08-06" for "gpt-4o".
     */
    public function get(string $model, string ...$fallbacks): ?Price
    {
        $models = [$model, ...$fallbacks];

        foreach ($models as $name) {
            if (isset($this->prices[$name])) {
                return $this->prices[$name];
            }
        }

        foreach ($models as $name) {
            $undated = preg_replace('/-(?:\d{4}-\d{2}-\d{2}|\d{8})$/', '', $name);
            if (isset($this->prices[$undated])) {
                return $this->prices[$undated];
            }
        }

        return null;
    }
}
