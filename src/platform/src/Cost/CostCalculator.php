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

use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * Calculates the cost of token usage based on a price table.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CostCalculator
{
    public function __construct(
        private readonly PriceTable $prices,
    ) {
    }

    /**
     * @param string $model the model the usage is billed for, unless the usage itself names one
     *
     * @return Cost|null null if the price of the model is unknown
     */
    public function calculate(string $model, TokenUsageInterface $usage): ?Cost
    {
        if ($usage instanceof TokenUsageAggregation) {
            $total = new Cost(0.0, $this->prices->getCurrency());
            foreach ($usage->getTokenUsages() as $single) {
                $cost = $this->calculate($model, $single);
                if (null === $cost) {
                    return null;
                }

                $total = $total->add($cost);
            }

            return $total;
        }

        $price = $this->prices->get($usage->getModel() ?? $model) ?? $this->prices->get($model);
        if (null === $price) {
            return null;
        }

        $cacheRead = $usage->getCacheReadTokens() ?? 0;
        $cacheWrite = $usage->getCacheCreationTokens() ?? 0;
        // prompt tokens include the cached ones for most providers, they are billed at their own price
        $input = max(0, ($usage->getPromptTokens() ?? 0) - $cacheRead - $cacheWrite);
        $output = ($usage->getCompletionTokens() ?? 0) + ($usage->getThinkingTokens() ?? 0);

        $amount = ($input * $price->getInput()
            + $output * $price->getOutput()
            + $cacheRead * $price->getCacheRead()
            + $cacheWrite * $price->getCacheWrite()) / 1_000_000;

        return new Cost($amount, $this->prices->getCurrency());
    }
}
