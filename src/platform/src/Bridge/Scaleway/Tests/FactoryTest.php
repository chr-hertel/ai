<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Scaleway\Factory;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class FactoryTest extends TestCase
{
    public function testItCreatesPlatformWithDefaultSettings()
    {
        $platform = Factory::createPlatform('scaleway-test-api-key');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithCustomHttpClient()
    {
        $httpClient = new MockHttpClient();
        $platform = Factory::createPlatform('scaleway-test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $platform = Factory::createPlatform('scaleway-test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItThrowsExceptionWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        Factory::createPlatform('');
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $response = self::textResponse();

        Factory::createPlatform('scaleway-api-key', new MockHttpClient($response))
            ->invoke('deepseek-r1-distill-llama-70b', new MessageBag(Message::ofUser('test message')), ['temperature' => 1]);

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('https://api.scaleway.ai/v1/chat/completions', $response->getRequestUrl());
        $this->assertSame('Authorization: Bearer scaleway-api-key', $response->getRequestOptions()['normalized_headers']['authorization'][0]);
        $this->assertSame([
            'temperature' => 1,
            'messages' => [['role' => 'user', 'content' => 'test message']],
            'model' => 'deepseek-r1-distill-llama-70b',
        ], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $completions = self::textResponse();
        $embeddings = new JsonMockResponse(['data' => []]);

        $platform = Factory::createPlatform('scaleway-api-key', new MockHttpClient([$completions, $embeddings]), baseUrl: 'https://x.example.com/');
        $platform->invoke('deepseek-r1-distill-llama-70b', new MessageBag(Message::ofUser('Hello')));
        $platform->invoke('bge-multilingual-gemma2', 'Hello');

        $this->assertSame('https://x.example.com/v1/chat/completions', $completions->getRequestUrl());
        $this->assertSame('https://x.example.com/v1/embeddings', $embeddings->getRequestUrl());
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $response = self::textResponse();

        Factory::createPlatform('scaleway-api-key', new MockHttpClient($response))
            ->invoke('deepseek-r1-distill-llama-70b', new MessageBag(Message::ofUser("tool output \xB1 here")));

        $options = $response->getRequestOptions();
        $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
        $this->assertJson($options['body']);
        $this->assertStringContainsString('tool output \ufffd here', $options['body']);
    }

    public function testItIsExecutingTheCorrectEmbeddingsRequest()
    {
        $response = self::invokeEmbeddings('test text');

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('https://api.scaleway.ai/v1/embeddings', $response->getRequestUrl());
        $this->assertSame('Authorization: Bearer scaleway-api-key', $response->getRequestOptions()['normalized_headers']['authorization'][0]);
        $this->assertSame(['model' => 'bge-multilingual-gemma2', 'input' => 'test text'], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testItIsExecutingTheCorrectEmbeddingsRequestWithCustomOptions()
    {
        $response = self::invokeEmbeddings('test text', ['dimensions' => 256]);

        $this->assertSame(['dimensions' => 256, 'model' => 'bge-multilingual-gemma2', 'input' => 'test text'], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testItIsExecutingTheCorrectEmbeddingsRequestWithArrayInput()
    {
        $response = self::invokeEmbeddings(['text1', 'text2', 'text3']);

        $this->assertSame(['model' => 'bge-multilingual-gemma2', 'input' => ['text1', 'text2', 'text3']], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testItConvertsAnEmbeddingsResponseToAVectorResult()
    {
        $response = new JsonMockResponse([
            'object' => 'list',
            'data' => [
                ['object' => 'embedding', 'index' => 0, 'embedding' => [0.3, 0.4, 0.4]],
                ['object' => 'embedding', 'index' => 1, 'embedding' => [0.0, 0.0, 0.2]],
            ],
        ]);

        $vectorResult = Factory::createPlatform('scaleway-test-api-key', new MockHttpClient($response))
            ->invoke('bge-multilingual-gemma2', 'Hello')
            ->getResult();

        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $convertedContent = $vectorResult->getContent();
        $this->assertCount(2, $convertedContent);
        $this->assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
    }

    public function testItConvertsAnEmptyEmbeddingsResponseToAnEmptyVectorResult()
    {
        $vectorResult = Factory::createPlatform('scaleway-test-api-key', new MockHttpClient(new JsonMockResponse(['object' => 'list', 'data' => []])))
            ->invoke('bge-multilingual-gemma2', 'Hello')
            ->getResult();

        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertSame([], $vectorResult->getContent());
    }

    private static function textResponse(): MockResponse
    {
        return new JsonMockResponse(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Hello'], 'finish_reason' => 'stop']]]);
    }

    /**
     * @param string|string[]      $input
     * @param array<string, mixed> $options
     */
    private static function invokeEmbeddings(string|array $input, array $options = []): MockResponse
    {
        $response = new JsonMockResponse(['data' => []]);

        Factory::createPlatform('scaleway-api-key', new MockHttpClient($response))
            ->invoke('bge-multilingual-gemma2', $input, $options);

        return $response;
    }
}
