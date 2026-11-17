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
            'claude-sonnet-4-5' => ['input' => 3.0, 'output' => 15.0, 'cache_read' => 0.3, 'cache_write' => 3.75],
        ]));

        $cost = $calculator->calculate('claude-sonnet-4-5', new TokenUsage(
            promptTokens: 3_000_000,
            completionTokens: 100_000,
            cacheCreationTokens: 1_000_000,
            cacheReadTokens: 1_000_000,
        ));

        $this->assertNotNull($cost);
        // 1M uncached input * 3 + 1M cache write * 3.75 + 1M cache read * 0.3 + 0.1M output * 15
        $this->assertEqualsWithDelta(8.55, $cost->getAmount(), 0.0001);
        $this->assertSame('USD', $cost->getCurrency());
    }

    public function testAnthropicStyleUsageBillsUncachedInputAndCacheReads()
    {
        $calculator = new CostCalculator(PriceTable::fromArray([
            'claude-sonnet-4-5' => ['input' => 3.0, 'output' => 15.0, 'cache_read' => 0.3],
        ]));

        // input_tokens: 1M, cache_read_input_tokens: 1M, as the Anthropic bridge reports it
        $cost = $calculator->calculate('claude-sonnet-4-5', new TokenUsage(promptTokens: 2_000_000, cachedTokens: 1_000_000, cacheReadTokens: 1_000_000));

        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(3.3, $cost->getAmount(), 0.0001);
    }

    public function testOpenAiStyleCachedTokensAreDiscounted()
    {
        $calculator = new CostCalculator(PriceTable::fromArray([
            'gpt-4o' => ['input' => 2.5, 'output' => 10.0, 'cache_read' => 1.25],
        ]));

        // input_tokens: 2M with input_tokens_details.cached_tokens: 1M, as the OpenAI bridge reports it
        $cost = $calculator->calculate('gpt-4o', new TokenUsage(promptTokens: 2_000_000, cachedTokens: 1_000_000, cacheReadTokens: 1_000_000));

        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(3.75, $cost->getAmount(), 0.0001);
    }

    public function testThinkingTokensAreBilledAsPartOfTheCompletion()
    {
        $calculator = new CostCalculator(PriceTable::fromArray(['o3' => ['input' => 2.0, 'output' => 8.0]]));

        $cost = $calculator->calculate('o3', new TokenUsage(promptTokens: 1_000_000, completionTokens: 1_000_000, thinkingTokens: 800_000));

        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(10.0, $cost->getAmount(), 0.0001);
    }

    public function testSnapshotOfAConfiguredModelIsPriced()
    {
        $calculator = new CostCalculator(PriceTable::fromArray(['gpt-4o' => ['input' => 2.5, 'output' => 10.0], 'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.6]]));

        $cost = $calculator->calculate('gpt-4o-mini', new TokenUsage(promptTokens: 1_000_000, model: 'gpt-4o-mini-2024-07-18'));

        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(0.15, $cost->getAmount(), 0.0001);
    }

    public function testExactlyPricedRequestedModelWinsOverAnUndatedReportedModel()
    {
        $calculator = new CostCalculator(PriceTable::fromArray(['gpt-4o' => ['input' => 2.5, 'output' => 10.0], 'my-gpt' => ['input' => 1.0, 'output' => 1.0]]));

        $cost = $calculator->calculate('my-gpt', new TokenUsage(promptTokens: 1_000_000, model: 'gpt-4o-2024-08-06'));

        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(1.0, $cost->getAmount(), 0.0001);
    }

    public function testModelSharingAPricedPrefixHasNoCost()
    {
        $calculator = new CostCalculator(PriceTable::fromArray(['gpt-4o' => ['input' => 2.5, 'output' => 10.0]]));

        $this->assertNull($calculator->calculate('gpt-4o-mini', new TokenUsage(promptTokens: 1_000_000, model: 'gpt-4o-mini-2024-07-18')));
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

    public function testAggregationIsPricedPerCall()
    {
        $calculator = new CostCalculator(PriceTable::fromArray([
            'gpt-4o' => ['input' => 2.5, 'output' => 10.0, 'cache_read' => 1.25],
            'text-embedding-3-small' => ['input' => 0.02, 'output' => 0.0],
        ]));

        $cost = $calculator->calculate('gpt-4o', new TokenUsageAggregation([
            new TokenUsage(promptTokens: 2_000_000, completionTokens: 1_000_000, thinkingTokens: 500_000, cacheReadTokens: 1_000_000, model: 'gpt-4o-2024-08-06'),
            new TokenUsage(promptTokens: 1_000_000, model: 'text-embedding-3-small'),
        ]));

        $this->assertNotNull($cost);
        // gpt-4o: 1M uncached * 2.5 + 1M cache read * 1.25 + 1M output * 10, embeddings: 1M * 0.02
        $this->assertEqualsWithDelta(13.77, $cost->getAmount(), 0.0001);
    }

    public function testUnknownModelHasNoCost()
    {
        $calculator = new CostCalculator(new PriceTable([]));

        $this->assertNull($calculator->calculate('unknown', new TokenUsage(promptTokens: 10)));
    }
}
