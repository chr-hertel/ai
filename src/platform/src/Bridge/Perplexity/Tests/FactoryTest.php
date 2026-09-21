<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Perplexity\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class FactoryTest extends TestCase
{
    public function testItCreatesPlatformWithDefaultSettings()
    {
        $platform = Factory::createPlatform('pplx-test-api-key');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithCustomHttpClient()
    {
        $httpClient = new MockHttpClient();
        $platform = Factory::createPlatform('pplx-test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $platform = Factory::createPlatform('pplx-test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    #[TestWith(['https://api.perplexity.ai', 'https://api.perplexity.ai/chat/completions'])]
    #[TestWith(['https://perplexity.example.com/', 'https://perplexity.example.com/chat/completions'])]
    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized(string $baseUrl, string $expectedUrl)
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url) use ($expectedUrl): MockResponse {
            self::assertSame($expectedUrl, $url);

            return new MockResponse();
        });

        Factory::createPlatform('pplx-api-key', $httpClient, baseUrl: $baseUrl)->invoke('sonar', new MessageBag(Message::ofUser('Hello')));

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
