<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\ResponsesClient;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

final class ResponsesClientTest extends TestCase
{
    public function testItWrapsHttpClientInEventSourceHttpClient()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new ResponsesClient(new HttpTransport($httpClient, 'sk-valid-api-key'));

        $this->assertInstanceOf(ResponsesClient::class, $modelClient);
    }

    public function testItAcceptsEventSourceHttpClientDirectly()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $modelClient = new ResponsesClient(new HttpTransport($httpClient, 'sk-valid-api-key'));

        $this->assertInstanceOf(ResponsesClient::class, $modelClient);
    }

    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = self::client();

        $this->assertTrue($modelClient->supports(new Gpt('gpt-4o')));
    }

    public function testStringPayloadThrowsException()
    {
        $modelClient = self::client();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given');

        $modelClient->request(new Gpt('gpt-4o'), 'string payload');
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/responses', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":1,"model":"gpt-4o","messages":[{"role":"user","content":"test message"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new Gpt('gpt-4o'), ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'test message']]], ['temperature' => 1]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            self::assertJson($options['body']);
            self::assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new Gpt('gpt-4o'), ['messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/responses', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":0.7,"text":{"format":{"name":"foo","schema":[],"type":"json"}},"model":"gpt-4o","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };

        $options = [
            'temperature' => 0.7,
            'response_format' => [
                'type' => 'json',
                'json_schema' => [
                    'name' => 'foo',
                    'schema' => []],
            ],
        ];

        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new Gpt('gpt-4o'), ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'Hello']]], $options);
    }

    #[TestWith(['EU', 'https://eu.api.openai.com/v1/responses'])]
    #[TestWith(['US', 'https://us.api.openai.com/v1/responses'])]
    #[TestWith([null, 'https://api.openai.com/v1/responses'])]
    public function testItUsesCorrectBaseUrl(?string $region, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ResponsesClient(new HttpTransport($httpClient, 'sk-api-key', $region));
        $modelClient->request(new Gpt('gpt-4o'), ['messages' => []], []);
    }

    public function testThrowsExceedContextSizeExceptionOnContextLengthExceeded()
    {
        $client = self::client(new MockHttpClient(new JsonMockResponse([
            'error' => [
                'message' => "This model's maximum context length is 128000 tokens. However, your messages resulted in 145575 tokens.",
                'type' => 'invalid_request_error',
                'param' => 'messages',
                'code' => 'context_length_exceeded',
            ],
        ], ['http_code' => 400])));

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('maximum context length is 128000 tokens');

        $client->convert($client->request(new Gpt('gpt-4o'), ['input' => []]));
    }

    public function testStreamYieldsToolCallComplete()
    {
        $httpResponse = $this->createStub(HttpResponse::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'response.completed',
                'response' => [
                    'output' => [
                        [
                            'type' => 'function_call',
                            'id' => 'call_123',
                            'name' => 'get_weather',
                            'arguments' => '{"city":"Berlin"}',
                        ],
                    ],
                ],
            ],
        ];

        $streamResult = self::client()->convert(new InMemoryRawResult([], $events, $httpResponse), ['stream' => true]);
        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $this->assertInstanceOf(MetadataDelta::class, $chunks[1]);
        $this->assertTrue($chunks[1]->getValue()->is(FinishReasonCase::TOOL_CALL));
        $this->assertSame('call_123', $chunks[0]->getToolCalls()[0]->getId());
        $this->assertSame('get_weather', $chunks[0]->getToolCalls()[0]->getName());
        $this->assertSame(['city' => 'Berlin'], $chunks[0]->getToolCalls()[0]->getArguments());
    }

    public function testStreamYieldsToolCallCompleteFromOutputItemDone()
    {
        $httpResponse = $this->createStub(HttpResponse::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'response.output_item.done',
                'item' => [
                    'type' => 'function_call',
                    'id' => 'call_123',
                    'name' => 'get_weather',
                    'arguments' => '{"city":"Berlin"}',
                ],
            ],
            [
                'type' => 'response.completed',
                'response' => [
                    'output' => [],
                ],
            ],
        ];

        $streamResult = self::client()->convert(new InMemoryRawResult([], $events, $httpResponse), ['stream' => true]);
        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $this->assertInstanceOf(MetadataDelta::class, $chunks[1]);
        $this->assertTrue($chunks[1]->getValue()->is(FinishReasonCase::TOOL_CALL));
        $this->assertSame('call_123', $chunks[0]->getToolCalls()[0]->getId());
        $this->assertSame('get_weather', $chunks[0]->getToolCalls()[0]->getName());
        $this->assertSame(['city' => 'Berlin'], $chunks[0]->getToolCalls()[0]->getArguments());
    }

    private static function client(MockHttpClient $httpClient = new MockHttpClient()): ResponsesClient
    {
        return new ResponsesClient(new HttpTransport($httpClient, 'sk-api-key'));
    }
}
