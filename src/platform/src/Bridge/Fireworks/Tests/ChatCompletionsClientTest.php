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
use Symfony\AI\Platform\Bridge\Fireworks\ChatCompletionsClient;
use Symfony\AI\Platform\Bridge\Fireworks\Factory;
use Symfony\AI\Platform\Bridge\Fireworks\Fireworks;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\InvalidRequestException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ChatCompletionsClientTest extends TestCase
{
    public function testSupportsChatFireworksModel()
    {
        $client = self::createClient(new MockHttpClient());

        $this->assertTrue($client->supports(new Fireworks('accounts/fireworks/models/kimi-k2p6', [Capability::INPUT_MESSAGES])));
    }

    public function testDoesNotSupportOtherModels()
    {
        $client = self::createClient(new MockHttpClient());

        $this->assertFalse($client->supports(new Model('gpt-4')));
        $this->assertFalse($client->supports(new Fireworks('accounts/fireworks/models/qwen3-reranker-8b', [Capability::RERANKING])));
    }

    public function testRequestSendsChatCompletionsToCorrectEndpoint()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            self::assertSame('POST', $method);
            self::assertSame('https://api.fireworks.ai/inference/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);

            $body = json_decode($options['body'], true);
            self::assertArrayHasKey('messages', $body);
            self::assertSame('accounts/fireworks/models/kimi-k2p6', $body['model']);

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']]]);
        });

        $model = new Fireworks('accounts/fireworks/models/kimi-k2p6', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]);

        self::createClient($httpClient)->request($model, ['messages' => [['role' => 'user', 'content' => 'Hi']]]);
        $this->assertTrue($requestMade);
    }

    public function testRequestMergesOptionsWithPayload()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            $body = json_decode($options['body'], true);
            self::assertArrayHasKey('messages', $body);
            self::assertSame(0.7, $body['temperature']);

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']]]);
        });

        $model = new Fireworks('accounts/fireworks/models/kimi-k2p6', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]);

        self::createClient($httpClient)->request(
            $model,
            ['messages' => [['role' => 'user', 'content' => 'Hi']]],
            ['temperature' => 0.7],
        );
        $this->assertTrue($requestMade);
    }

    public function testConvertTextResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'Hello, how can I help you?'],
                    'finish_reason' => 'stop',
                ],
            ],
        ]));

        $result = self::createClient($httpClient)->convert(new RawHttpResult($httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/chat/completions')));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, how can I help you?', $result->getContent());
    }

    public function testConvertToolCallResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_abc123',
                                'type' => 'function',
                                'function' => ['name' => 'get_weather', 'arguments' => '{"location":"Paris"}'],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]));

        $result = self::createClient($httpClient)->convert(new RawHttpResult($httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/chat/completions')));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertSame('call_abc123', $result->getContent()[0]->getId());
        $this->assertSame('get_weather', $result->getContent()[0]->getName());
        $this->assertSame(['location' => 'Paris'], $result->getContent()[0]->getArguments());
    }

    public function testConvertThrowsContentFilterException()
    {
        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage('Content filtered');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => ['code' => 'content_filter', 'message' => 'Content filtered'],
        ]));

        self::createClient($httpClient)->convert(new RawHttpResult($httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/chat/completions')));
    }

    public function testConvertThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('This model maximum context length is 65536 tokens');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => [
                'message' => 'This model maximum context length is 65536 tokens. However, you requested 600018 tokens.',
                'type' => 'invalid_request_error',
                'code' => 'invalid_request_error',
            ],
        ], ['http_code' => 400]));

        self::createClient($httpClient)->convert(new RawHttpResult($httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/chat/completions')));
    }

    public function testConvertThrowsInvalidRequestException()
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid request');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => ['code' => 'invalid_request_error', 'message' => 'Invalid request'],
        ]));

        self::createClient($httpClient)->convert(new RawHttpResult($httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/chat/completions')));
    }

    public function testStreamingTextWithoutReasoningUnchanged()
    {
        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hello, ']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'world!']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
        ];

        $streamResult = self::createClient(new MockHttpClient())->convert(new InMemoryRawResult(dataStream: $events), ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = iterator_to_array($streamResult->getContent(), false);

        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello, ', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame('world!', $chunks[1]->getText());
        $this->assertInstanceOf(MetadataDelta::class, $chunks[2]);
        $this->assertSame('finish_reason', $chunks[2]->getKey());
        $this->assertSame(FinishReasonCase::STOP, $chunks[2]->getValue()->getCase());
    }

    public function testStreamingReasoningContentYieldsThinkingComplete()
    {
        $events = [
            ['choices' => [['index' => 0, 'delta' => ['reasoning_content' => 'Let me ']]]],
            ['choices' => [['index' => 0, 'delta' => ['reasoning_content' => 'think about this.']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'The answer ']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'is 42.']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
        ];

        $streamResult = self::createClient(new MockHttpClient())->convert(new InMemoryRawResult(dataStream: $events), ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = iterator_to_array($streamResult->getContent(), false);

        $thinkingDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingDelta));
        $this->assertCount(2, $thinkingDeltas);
        $this->assertSame('Let me ', $thinkingDeltas[0]->getThinking());
        $this->assertSame('think about this.', $thinkingDeltas[1]->getThinking());

        $thinkingCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes);
        $this->assertSame('Let me think about this.', $thinkingCompletes[0]->getThinking());

        $textDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof TextDelta));
        $this->assertCount(2, $textDeltas);
        $this->assertSame('The answer ', $textDeltas[0]->getText());
        $this->assertSame('is 42.', $textDeltas[1]->getText());
    }

    private static function createClient(HttpClientInterface $httpClient): ChatCompletionsClient
    {
        return new ChatCompletionsClient(new HttpTransport($httpClient, Factory::DEFAULT_INFERENCE_ENDPOINT, 'test-api-key'));
    }
}
