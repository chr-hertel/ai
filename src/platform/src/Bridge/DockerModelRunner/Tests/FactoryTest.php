<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DockerModelRunner\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\DockerModelRunner\Factory;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FactoryTest extends TestCase
{
    public function testItConvertsACompletionsResponse()
    {
        $response = self::textResponse();

        $result = self::invokeCompletions($response)->getResult();

        $this->assertSame('http://localhost:12434/engines/v1/chat/completions', $response->getRequestUrl());
        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testItIsExecutingTheCorrectCompletionsRequest()
    {
        $response = self::textResponse();

        self::invokeCompletions($response);

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame([
            'messages' => [['role' => 'user', 'content' => 'Hello']],
            'model' => 'ai/gemma3',
        ], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testItMergesOptionsWithCompletionsPayload()
    {
        $response = self::textResponse();

        self::invokeCompletions($response, ['temperature' => 0.7]);

        $this->assertSame([
            'temperature' => 0.7,
            'messages' => [['role' => 'user', 'content' => 'Hello']],
            'model' => 'ai/gemma3',
        ], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $completions = self::textResponse();
        $embeddings = new JsonMockResponse(['data' => []]);

        $platform = Factory::createPlatform('http://localhost:1234/', new MockHttpClient([$completions, $embeddings]));
        $platform->invoke('ai/gemma3', new MessageBag(Message::ofUser('Hello')));
        $platform->invoke('ai/nomic-embed-text-v1.5', 'Hello');

        $this->assertSame('http://localhost:1234/engines/v1/chat/completions', $completions->getRequestUrl());
        $this->assertSame('http://localhost:1234/engines/v1/embeddings', $embeddings->getRequestUrl());
    }

    public function testItStreamsThroughAPlainHttpClient()
    {
        $this->assertStreamsHello(new MockHttpClient(self::streamResponse()));
    }

    public function testItStreamsThroughAnExistingEventSourceHttpClient()
    {
        $this->assertStreamsHello(new EventSourceHttpClient(new MockHttpClient(self::streamResponse())));
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $response = self::textResponse();

        Factory::createPlatform(httpClient: new MockHttpClient($response))
            ->invoke('ai/gemma3', new MessageBag(Message::ofUser("tool output \xB1 here")));

        $options = $response->getRequestOptions();
        $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
        $this->assertJson($options['body']);
        $this->assertStringContainsString('tool output \ufffd here', $options['body']);
    }

    public function testItThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('the request exceeds the available context size');

        self::invokeCompletions(new JsonMockResponse([
            'error' => [
                'code' => 400,
                'message' => 'the request exceeds the available context size, try increasing it',
                'type' => 'exceed_context_size_error',
                'n_prompt_tokens' => 5000,
                'n_ctx' => 4096,
            ],
        ], ['http_code' => 400]))->getResult();
    }

    public function testItThrowsBadRequestExceptionOnOtherBadRequests()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid request');

        self::invokeCompletions(new JsonMockResponse([
            'error' => ['code' => 400, 'message' => 'Invalid request', 'type' => 'invalid_request_error'],
        ], ['http_code' => 400]))->getResult();
    }

    #[TestWith(['Model not found'])]
    #[TestWith(['MODEL NOT FOUND'])]
    #[TestWith(['Not found'])]
    public function testItThrowsModelNotFoundExceptionOn404(string $body)
    {
        $this->expectException(ModelNotFoundException::class);

        self::invokeCompletions(new MockResponse($body, ['http_code' => 404]))->getResult();
    }

    #[TestWith(['Model not found'])]
    #[TestWith(['Not found'])]
    public function testItThrowsModelNotFoundExceptionOn404ForEmbeddings(string $body)
    {
        $this->expectException(ModelNotFoundException::class);

        self::invokeEmbeddings(new MockResponse($body, ['http_code' => 404]))->getResult();
    }

    public function testThrowsServerExceptionOnServerErrorStatusBeforeStreaming()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        self::invokeCompletions(new MockResponse('Service Unavailable', ['http_code' => 500]), ['stream' => true])->getResult();
    }

    public function testItIsExecutingTheCorrectEmbeddingsRequest()
    {
        $response = new JsonMockResponse(['data' => []]);

        self::invokeEmbeddings($response, 'Hello, world!');

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('http://localhost:12434/engines/v1/embeddings', $response->getRequestUrl());
        $this->assertSame(['model' => 'ai/nomic-embed-text-v1.5', 'input' => 'Hello, world!'], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testItMergesOptionsWithEmbeddingsPayload()
    {
        $response = new JsonMockResponse(['data' => []]);

        self::invokeEmbeddings($response, 'Hello, world!', ['custom_option' => 'value']);

        $this->assertSame(['custom_option' => 'value', 'model' => 'ai/nomic-embed-text-v1.5', 'input' => 'Hello, world!'], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testItHandlesArrayEmbeddingsInput()
    {
        $response = new JsonMockResponse(['data' => []]);

        self::invokeEmbeddings($response, ['Hello', 'world']);

        $this->assertSame(['model' => 'ai/nomic-embed-text-v1.5', 'input' => ['Hello', 'world']], json_decode($response->getRequestOptions()['body'], true));
    }

    public function testItConvertsAnEmbeddingsResponseToAVectorResult()
    {
        $vectorResult = self::invokeEmbeddings(new JsonMockResponse([
            'object' => 'list',
            'data' => [
                ['object' => 'embedding', 'index' => 0, 'embedding' => [0.3, 0.4, 0.4]],
                ['object' => 'embedding', 'index' => 1, 'embedding' => [0.0, 0.0, 0.2]],
            ],
        ]))->getResult();

        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $convertedContent = $vectorResult->getContent();
        $this->assertCount(2, $convertedContent);
        $this->assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
    }

    public function testItConvertsAnEmptyEmbeddingsResponseToAnEmptyVectorResult()
    {
        $vectorResult = self::invokeEmbeddings(new JsonMockResponse(['object' => 'list', 'data' => []]))->getResult();

        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertSame([], $vectorResult->getContent());
    }

    public function testItThrowsExceptionWhenEmbeddingsResponseDoesNotContainData()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain data.');

        self::invokeEmbeddings(new JsonMockResponse(['invalid' => 'response']))->getResult();
    }

    private function assertStreamsHello(HttpClientInterface $httpClient): void
    {
        $deltas = Factory::createPlatform(httpClient: $httpClient)
            ->invoke('ai/gemma3', new MessageBag(Message::ofUser('Hello')), ['stream' => true])
            ->asStream();

        $texts = array_values(array_filter(iterator_to_array($deltas, false), static fn ($delta) => $delta instanceof TextDelta));

        $this->assertCount(1, $texts);
        $this->assertSame('Hello', $texts[0]->getText());
    }

    private static function textResponse(): MockResponse
    {
        return new JsonMockResponse([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Hello world'], 'finish_reason' => 'stop'],
            ],
        ]);
    }

    private static function streamResponse(): MockResponse
    {
        return new MockResponse(
            "data: {\"choices\":[{\"index\":0,\"delta\":{\"content\":\"Hello\"}}]}\n\n"
            ."data: {\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"stop\"}]}\n\n"
            ."data: [DONE]\n\n",
            ['response_headers' => ['content-type' => 'text/event-stream']],
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function invokeCompletions(MockResponse $response, array $options = []): DeferredResult
    {
        return Factory::createPlatform(httpClient: new MockHttpClient($response))
            ->invoke('ai/gemma3', new MessageBag(Message::ofUser('Hello')), $options);
    }

    /**
     * @param string|string[]      $input
     * @param array<string, mixed> $options
     */
    private static function invokeEmbeddings(MockResponse $response, string|array $input = 'Hello', array $options = []): DeferredResult
    {
        return Factory::createPlatform(httpClient: new MockHttpClient($response))
            ->invoke('ai/nomic-embed-text-v1.5', $input, $options);
    }
}
