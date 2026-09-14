<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Bridge\Mistral\SpeechToText;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FactoryTest extends TestCase
{
    private const RATE_LIMIT_HEADERS = [
        'x-ratelimit-limit-tokens-minute' => '1000',
        'x-ratelimit-limit-tokens-month' => '1000000',
    ];

    public function testChatCompletionsRequestCarriesMistralHeaders()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('https://api.mistral.ai/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer test-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            self::assertSame('Accept: application/json', $options['normalized_headers']['accept'][0]);

            return new MockResponse();
        });

        Factory::createPlatform('test-key', $httpClient)->invoke('mistral-large-latest', new MessageBag(Message::ofUser('Hello')));

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return new MockResponse();
        });

        $platform = Factory::createPlatform('test-key', $httpClient, baseUrl: 'https://mistral.example.com/');
        $platform->invoke('mistral-large-latest', new MessageBag(Message::ofUser('Hello')));
        $platform->invoke('mistral-embed', 'Hello');
        $platform->invoke(new SpeechToText('voxtral-mini-latest'), Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3'));

        $this->assertSame([
            'https://mistral.example.com/v1/chat/completions',
            'https://mistral.example.com/v1/embeddings',
            'https://mistral.example.com/v1/audio/transcriptions',
        ], $urls);
    }

    public function testChatCompletionsTokenUsageCarriesRateLimits()
    {
        $platform = Factory::createPlatform('test-key', new MockHttpClient(new JsonMockResponse([
            'model' => 'mistral-large-latest',
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Hello'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
        ], ['response_headers' => self::RATE_LIMIT_HEADERS])));

        $tokenUsage = $platform->invoke('mistral-large-latest', new MessageBag(Message::ofUser('Hello')))->getResult()->getMetadata()->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1000, $tokenUsage->getRemainingTokensMinute());
        $this->assertSame(1000000, $tokenUsage->getRemainingTokensMonth());
        $this->assertSame(30, $tokenUsage->getTotalTokens());
    }

    public function testEmbeddingsTokenUsageCarriesRateLimits()
    {
        $platform = Factory::createPlatform('test-key', new MockHttpClient(new JsonMockResponse([
            'model' => 'mistral-embed',
            'data' => [['object' => 'embedding', 'index' => 0, 'embedding' => [0.1, 0.2]]],
            'usage' => ['prompt_tokens' => 10, 'total_tokens' => 10],
        ], ['response_headers' => self::RATE_LIMIT_HEADERS])));

        $tokenUsage = $platform->invoke('mistral-embed', 'Hello')->getResult()->getMetadata()->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1000, $tokenUsage->getRemainingTokensMinute());
        $this->assertSame(1000000, $tokenUsage->getRemainingTokensMonth());
        $this->assertSame(10, $tokenUsage->getTotalTokens());
    }
}
