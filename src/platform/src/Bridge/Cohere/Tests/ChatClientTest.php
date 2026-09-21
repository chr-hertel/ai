<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\ChatClient;
use Symfony\AI\Platform\Bridge\Cohere\Cohere;
use Symfony\AI\Platform\Bridge\Cohere\Embeddings;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\MalformedToolCallException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ChatClientTest extends TestCase
{
    public function testItSupportsCohereModel()
    {
        $client = new ChatClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Cohere('command-a-03-2025')));
    }

    public function testItDoesNotSupportEmbeddingsModel()
    {
        $client = new ChatClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Embeddings('embed-english-v3.0')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.cohere.com/v2/chat', $url);
            $this->assertStringContainsString('Bearer test-key', $options['normalized_headers']['authorization'][0]);
            $this->assertSame(['temperature' => 0.5, 'model' => 'command-a-03-2025', 'messages' => []], json_decode($options['body'], true));

            return new MockResponse();
        }]);

        $client = new ChatClient($httpClient, 'test-key');

        $client->request(new Cohere('command-a-03-2025'), ['model' => 'command-a-03-2025', 'messages' => []], ['temperature' => 0.5]);
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url): MockResponse {
            $this->assertSame('https://x.example.com/v2/chat', $url);

            return new MockResponse();
        }]);

        $client = new ChatClient($httpClient, 'test-key', 'https://x.example.com/');
        $client->request(new Cohere('command-a-03-2025'), ['model' => 'command-a-03-2025', 'messages' => []]);
    }

    public function testStringPayloadThrowsException()
    {
        $client = new ChatClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given');

        $client->request(new Cohere('command-a-03-2025'), 'string payload');
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        }]);

        $client = new ChatClient($httpClient, 'test-key');

        $client->request(new Cohere('command-a-03-2025'), ['model' => 'command-a-03-2025', 'messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }

    public function testItThrowsExceptionOnNon200StatusCode()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        self::request(new MockResponse('Internal Server Error', ['http_code' => 500]));
    }

    public function testItThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('too many tokens');

        self::request(new JsonMockResponse([
            'message' => 'too many tokens: size limit exceeded by 213302 tokens. Try using shorter or fewer inputs. The limit for this model is 288000 tokens.',
        ], ['http_code' => 400]));
    }

    public function testItThrowsBadRequestExceptionOnOtherBadRequests()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('invalid request: messages must not be empty');

        self::request(new JsonMockResponse(['message' => 'invalid request: messages must not be empty'], ['http_code' => 400]));
    }

    public function testItThrowsAuthenticationExceptionOnUnauthorized()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('invalid api token');

        self::request(new JsonMockResponse(['message' => 'invalid api token'], ['http_code' => 401]));
    }

    public function testItThrowsModelNotFoundExceptionOnNotFound()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage("model 'command-z' not found");

        self::request(new JsonMockResponse(['message' => "model 'command-z' not found"], ['http_code' => 404]));
    }

    public function testThrowsRateLimitExceededExceptionWithRetryAfterHeader()
    {
        try {
            self::request(new JsonMockResponse(['message' => 'trial key rate limit exceeded'], ['http_code' => 429, 'response_headers' => ['retry-after' => '60']]));
            $this->fail('Expected a RateLimitExceededException to be thrown.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame(60, $e->getRetryAfter());
            $this->assertSame('Rate limit exceeded. trial key rate limit exceeded', $e->getMessage());
        }
    }

    public function testItThrowsRuntimeExceptionOnOtherUnexpectedStatusCode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 422: "unprocessable"');

        self::request(new MockResponse('unprocessable', ['http_code' => 422]));
    }

    public function testItConvertsCompleteResponseToTextResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'finish_reason' => 'COMPLETE',
            'message' => [
                'content' => [
                    ['type' => 'text', 'text' => 'Hello, world!'],
                ],
            ],
        ]);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, world!', $result->getContent());

        $finishReason = $result->getMetadata()->get('finish_reason');
        $this->assertInstanceOf(FinishReason::class, $finishReason);
        $this->assertSame(FinishReasonCase::STOP, $finishReason->getCase());
        $this->assertSame('COMPLETE', $finishReason->getRaw());
    }

    public function testItConvertsToolCallResponseToToolCallResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'finish_reason' => 'TOOL_CALL',
            'message' => [
                'tool_calls' => [
                    [
                        'id' => 'call_123',
                        'function' => [
                            'name' => 'get_weather',
                            'arguments' => '{"city":"Paris"}',
                        ],
                    ],
                ],
            ],
        ]);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertInstanceOf(ToolCall::class, $toolCalls[0]);
        $this->assertSame('call_123', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Paris'], $toolCalls[0]->getArguments());

        $finishReason = $result->getMetadata()->get('finish_reason');
        $this->assertInstanceOf(FinishReason::class, $finishReason);
        $this->assertSame(FinishReasonCase::TOOL_CALL, $finishReason->getCase());
    }

    public function testItThrowsClearExceptionForMalformedToolCallArguments()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'finish_reason' => 'TOOL_CALL',
            'message' => [
                'tool_calls' => [
                    [
                        'id' => 'call_123',
                        'function' => [
                            'name' => 'get_weather',
                            'arguments' => '{"city":Berlin}',
                        ],
                    ],
                ],
            ],
        ]);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');

        $this->expectException(MalformedToolCallException::class);
        $this->expectExceptionMessage('Cohere returned malformed JSON arguments for the "get_weather" tool: "Syntax error"');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItThrowsExceptionOnUnsupportedFinishReason()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'finish_reason' => 'UNKNOWN',
            'message' => [],
        ]);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported finish reason "UNKNOWN".');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItConvertsStreamWithTextContent()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(
            new InMemoryRawResult([], [
                ['type' => 'content-delta', 'delta' => ['message' => ['content' => ['text' => 'Hello']]]],
                ['type' => 'content-delta', 'delta' => ['message' => ['content' => ['text' => ', world!']]]],
                ['type' => 'message-end', 'delta' => []],
            ], $httpResponse),
            ['stream' => true],
        );

        $chunks = iterator_to_array($result->getContent(), false);
        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertSame(', world!', $chunks[1]->getText());
    }

    public function testItYieldsStreamFinishReasonAsMetadata()
    {
        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(
            new InMemoryRawResult([], [
                ['type' => 'content-delta', 'delta' => ['message' => ['content' => ['text' => 'Hello']]]],
                ['type' => 'message-end', 'delta' => ['finish_reason' => 'MAX_TOKENS']],
            ]),
            ['stream' => true],
        );

        $chunks = iterator_to_array($result->getContent(), false);
        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(MetadataDelta::class, $chunks[1]);
        $this->assertSame('finish_reason', $chunks[1]->getKey());
        $this->assertInstanceOf(FinishReason::class, $chunks[1]->getValue());
        $this->assertSame(FinishReasonCase::LENGTH, $chunks[1]->getValue()->getCase());
    }

    public function testItConvertsStreamWithToolCalls()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(
            new InMemoryRawResult([], [
                ['type' => 'tool-call-start', 'delta' => ['message' => ['tool_calls' => ['id' => 'call_1', 'function' => ['name' => 'get_time', 'arguments' => '']]]]],
                ['type' => 'tool-call-delta', 'delta' => ['message' => ['tool_calls' => ['function' => ['arguments' => '{"tz":']]]]],
                ['type' => 'tool-call-delta', 'delta' => ['message' => ['tool_calls' => ['function' => ['arguments' => '"UTC"}']]]]],
                ['type' => 'message-end', 'delta' => []],
            ], $httpResponse),
            ['stream' => true],
        );

        $chunks = iterator_to_array($result->getContent(), false);
        $this->assertCount(4, $chunks);

        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertSame('call_1', $chunks[0]->getId());
        $this->assertSame('get_time', $chunks[0]->getName());

        $this->assertInstanceOf(ToolInputDelta::class, $chunks[1]);
        $this->assertSame('call_1', $chunks[1]->getId());
        $this->assertSame('get_time', $chunks[1]->getName());
        $this->assertSame('{"tz":', $chunks[1]->getPartialJson());

        $this->assertInstanceOf(ToolInputDelta::class, $chunks[2]);
        $this->assertSame('"UTC"}', $chunks[2]->getPartialJson());

        $this->assertInstanceOf(ToolCallComplete::class, $chunks[3]);
        $toolCalls = $chunks[3]->getToolCalls();
        $this->assertSame('call_1', $toolCalls[0]->getId());
        $this->assertSame('get_time', $toolCalls[0]->getName());
        $this->assertSame(['tz' => 'UTC'], $toolCalls[0]->getArguments());
    }

    public function testItConvertsToolCallWithEmptyArguments()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'finish_reason' => 'TOOL_CALL',
            'message' => [
                'tool_calls' => [
                    [
                        'id' => 'call_456',
                        'function' => [
                            'name' => 'get_time',
                            'arguments' => '',
                        ],
                    ],
                ],
            ],
        ]);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_456', $toolCalls[0]->getId());
        $this->assertSame('get_time', $toolCalls[0]->getName());
        $this->assertSame([], $toolCalls[0]->getArguments());
    }

    public function testItConvertsStreamWithToolCallsWithEmptyArguments()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(
            new InMemoryRawResult([], [
                ['type' => 'tool-call-start', 'delta' => ['message' => ['tool_calls' => ['id' => 'call_1', 'function' => ['name' => 'get_time', 'arguments' => '']]]]],
                ['type' => 'message-end', 'delta' => []],
            ], $httpResponse),
            ['stream' => true],
        );

        $chunks = iterator_to_array($result->getContent(), false);
        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertSame('call_1', $chunks[0]->getId());
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[1]);
        $toolCalls = $chunks[1]->getToolCalls();
        $this->assertSame('call_1', $toolCalls[0]->getId());
        $this->assertSame('get_time', $toolCalls[0]->getName());
        $this->assertSame([], $toolCalls[0]->getArguments());
    }

    public function testItDoesNotAnnounceStreamedToolCallWithoutId()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(
            new InMemoryRawResult([], [
                ['type' => 'tool-call-start', 'delta' => ['message' => ['tool_calls' => ['function' => ['name' => 'get_time', 'arguments' => '']]]]],
                ['type' => 'tool-call-delta', 'delta' => ['message' => ['tool_calls' => ['function' => ['arguments' => '{"tz":']]]]],
                ['type' => 'tool-call-delta', 'delta' => ['message' => ['tool_calls' => ['function' => ['arguments' => '"UTC"}']]]]],
                ['type' => 'message-end', 'delta' => []],
            ], $httpResponse),
            ['stream' => true],
        );

        $chunks = iterator_to_array($result->getContent(), false);
        $this->assertCount(1, $chunks);

        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $toolCalls = $chunks[0]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('', $toolCalls[0]->getId());
        $this->assertSame('get_time', $toolCalls[0]->getName());
        $this->assertSame(['tz' => 'UTC'], $toolCalls[0]->getArguments());
    }

    public function testItSkipsToolInputDeltaWithoutPartialJson()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(
            new InMemoryRawResult([], [
                ['type' => 'tool-call-start', 'delta' => ['message' => ['tool_calls' => ['id' => 'call_1', 'function' => ['name' => 'get_time', 'arguments' => '']]]]],
                ['type' => 'tool-call-delta', 'delta' => ['message' => ['tool_calls' => ['function' => []]]]],
                ['type' => 'tool-call-delta', 'delta' => ['message' => ['tool_calls' => ['function' => ['arguments' => '{"tz":"UTC"}']]]]],
                ['type' => 'message-end', 'delta' => []],
            ], $httpResponse),
            ['stream' => true],
        );

        $chunks = iterator_to_array($result->getContent(), false);
        $this->assertCount(3, $chunks);

        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertSame('call_1', $chunks[0]->getId());

        $this->assertInstanceOf(ToolInputDelta::class, $chunks[1]);
        $this->assertSame('{"tz":"UTC"}', $chunks[1]->getPartialJson());

        $this->assertInstanceOf(ToolCallComplete::class, $chunks[2]);
        $toolCalls = $chunks[2]->getToolCalls();
        $this->assertSame(['tz' => 'UTC'], $toolCalls[0]->getArguments());
    }

    public function testItThrowsIncompleteStreamWhenMessageEndIsMissing()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(
            new InMemoryRawResult([], [
                ['type' => 'content-delta', 'delta' => ['message' => ['content' => ['text' => 'Hello']]]],
                // stream cut off: no message-end event
            ], $httpResponse),
            ['stream' => true],
        );

        $this->expectException(IncompleteStreamException::class);
        $this->expectExceptionMessage('Cohere stream ended before message-end.');

        iterator_to_array($result->getContent());
    }

    public function testItThrowsOnMessageEndError()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(
            new InMemoryRawResult([], [
                ['type' => 'content-delta', 'delta' => ['message' => ['content' => ['text' => 'Hello']]]],
                ['type' => 'message-end', 'delta' => ['finish_reason' => 'ERROR', 'error' => 'Something went wrong']],
            ], $httpResponse),
            ['stream' => true],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cohere stream error: "Something went wrong".');

        iterator_to_array($result->getContent());
    }

    public function testItThrowsOnStructuredMessageEndError()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(
            new InMemoryRawResult([], [
                ['type' => 'content-delta', 'delta' => ['message' => ['content' => ['text' => 'Hello']]]],
                ['type' => 'message-end', 'delta' => ['finish_reason' => 'ERROR', 'error' => ['message' => 'Something went wrong']]],
            ], $httpResponse),
            ['stream' => true],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cohere stream error: "Something went wrong".');

        iterator_to_array($result->getContent());
    }

    public function testItDoesNotThrowOnEmptyStream()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $converter = new ChatClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(
            new InMemoryRawResult([], [], $httpResponse),
            ['stream' => true],
        );

        $this->assertSame([], iterator_to_array($result->getContent()));
    }

    public function testGetTokenUsageExtractor()
    {
        $converter = new ChatClient(new MockHttpClient(), 'test-key');

        $this->assertNotNull($converter->getTokenUsageExtractor());
    }

    public function testThrowsServerExceptionOnServerErrorStatusBeforeStreaming()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        self::request(new MockResponse('Service Unavailable', ['http_code' => 500]), ['stream' => true]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function request(MockResponse $response, array $options = []): void
    {
        $client = new ChatClient(new MockHttpClient($response), 'test-key');
        $client->convert($client->request(new Cohere('command-a-03-2025'), ['model' => 'command-a-03-2025', 'messages' => []], $options), $options);
    }
}
