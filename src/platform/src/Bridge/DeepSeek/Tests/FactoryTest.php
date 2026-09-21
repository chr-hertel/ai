<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DeepSeek\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\DeepSeek\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FactoryTest extends TestCase
{
    public function testRequestSendsToCorrectEndpoint()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            self::assertSame('POST', $method);
            self::assertSame('https://api.deepseek.com/chat/completions', $url);
            self::assertArrayHasKey('normalized_headers', $options);
            self::assertArrayHasKey('authorization', $options['normalized_headers']);
            self::assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']]]);
        });

        $platform = Factory::createPlatform('test-api-key', $httpClient);

        $platform->invoke('deepseek-chat', new MessageBag(Message::ofUser('Hi')));
        $this->assertTrue($requestMade);
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url) use (&$requestMade) {
            $requestMade = true;
            self::assertSame('https://deepseek.example.com/chat/completions', $url);

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']]]);
        });

        $platform = Factory::createPlatform('test-api-key', $httpClient, baseUrl: 'https://deepseek.example.com/');
        $platform->invoke('deepseek-chat', new MessageBag(Message::ofUser('Hi')));
        $this->assertTrue($requestMade);
    }

    public function testRequestMergesOptionsWithPayload()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            $body = json_decode($options['body'], true);
            self::assertArrayHasKey('messages', $body);
            self::assertArrayHasKey('temperature', $body);
            self::assertSame(0.7, $body['temperature']);

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']]]);
        });

        $platform = Factory::createPlatform('test-api-key', $httpClient);

        $platform->invoke(
            'deepseek-chat',
            new MessageBag(Message::ofUser('Hi')),
            ['temperature' => 0.7]
        );
        $this->assertTrue($requestMade);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient(static function ($method, $url, $options) {
            self::assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            self::assertJson($options['body']);
            self::assertStringContainsString('tool output \ufffd here', $options['body']);

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']]]);
        });

        $platform = Factory::createPlatform('test-api-key', $httpClient);
        $platform->invoke('deepseek-chat', new MessageBag(Message::ofUser("tool output \xB1 here")));
    }
}
