<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Cost;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Cost\CostCalculator;
use Symfony\AI\Platform\Cost\PriceTable;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;

final class CostCalculatorTest extends TestCase
{
    public function testCalculatesInputOutputAndCacheTokens()
    {
        $calculator = new CostCalculator(PriceTable::fromArray([
            'claude-sonnet-4-5' => ['input' => 3.0, 'output' => 15.0, 'cache_read' => 0.3],
        ]));

        $cost = $calculator->calculate('claude-sonnet-4-5', new TokenUsage(promptTokens: 2_000_000, completionTokens: 100_000, cacheReadTokens: 1_000_000));

        $this->assertNotNull($cost);
        // 1M uncached input * 3 + 1M cache read * 0.3 + 0.1M output * 15
        $this->assertEqualsWithDelta(4.8, $cost->getAmount(), 0.0001);
        $this->assertSame('USD', $cost->getCurrency());
    }

    public function testSnapshotOfAConfiguredModelIsPriced()
    {
        $calculator = new CostCalculator(PriceTable::fromArray(['gpt-4o' => ['input' => 2.5, 'output' => 10.0], 'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.6]]));

        $cost = $calculator->calculate('gpt-4o-mini', new TokenUsage(promptTokens: 1_000_000, model: 'gpt-4o-mini-2024-07-18'));

        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(0.15, $cost->getAmount(), 0.0001);
    }

    public function testAggregationIsSummedUp()
    {
        $calculator = new CostCalculator(PriceTable::fromArray(['gpt-4o' => ['input' => 1.0, 'output' => 1.0]]));

        $cost = $calculator->calculate('gpt-4o', new TokenUsageAggregation([
            new TokenUsage(promptTokens: 500_000),
            new TokenUsage(completionTokens: 500_000),
        ]));

        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(1.0, $cost->getAmount(), 0.0001);
    }

    public function testUnknownModelHasNoCost()
    {
        $calculator = new CostCalculator(new PriceTable([]));

        $this->assertNull($calculator->calculate('unknown', new TokenUsage(promptTokens: 10)));
    }
}
