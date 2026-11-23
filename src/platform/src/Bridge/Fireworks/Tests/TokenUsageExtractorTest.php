<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Fireworks\TokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TokenUsageExtractorTest extends TestCase
{
    public function testItHandlesStreamResponsesWithoutProcessing()
    {
        $extractor = new TokenUsageExtractor();

        $this->assertNull($extractor->extract(new InMemoryRawResult(), ['stream' => true]));
    }

    public function testItDoesNothingWithoutUsageData()
    {
        $extractor = new TokenUsageExtractor();

        $this->assertNull($extractor->extract(new InMemoryRawResult(['some' => 'data'])));
    }

    public function testItExtractsTokenUsage()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertSame(20, $tokenUsage->getCompletionTokens());
        $this->assertSame(30, $tokenUsage->getTotalTokens());
    }

    public function testItHandlesMissingUsageFields()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult(['usage' => ['prompt_tokens' => 10]]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertNull($tokenUsage->getCompletionTokens());
        $this->assertNull($tokenUsage->getTotalTokens());
    }

    public function testItExtractsTokenUsageFromUsageArray()
    {
        $extractor = new TokenUsageExtractor();

        $tokenUsage = $extractor->extractFromArray([
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
        ]);

        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertSame(20, $tokenUsage->getCompletionTokens());
        $this->assertSame(30, $tokenUsage->getTotalTokens());
    }

    public function testItExtractsCachedAndReasoningTokens()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'model' => 'accounts/fireworks/models/kimi-k2p6',
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
                'prompt_tokens_details' => ['cached_tokens' => 4],
                'completion_tokens_details' => ['reasoning_tokens' => 7],
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(4, $tokenUsage->getCachedTokens());
        $this->assertSame(4, $tokenUsage->getCacheReadTokens());
        $this->assertSame(7, $tokenUsage->getThinkingTokens());
        $this->assertSame('accounts/fireworks/models/kimi-k2p6', $tokenUsage->getModel());
    }

    public function testItIgnoresNonArrayTokenDetails()
    {
        $extractor = new TokenUsageExtractor();

        $tokenUsage = $extractor->extractFromArray([
            'prompt_tokens' => 10,
            'prompt_tokens_details' => null,
            'completion_tokens_details' => null,
        ]);

        $this->assertNull($tokenUsage->getCachedTokens());
        $this->assertNull($tokenUsage->getThinkingTokens());
        $this->assertNull($tokenUsage->getModel());
    }
}
