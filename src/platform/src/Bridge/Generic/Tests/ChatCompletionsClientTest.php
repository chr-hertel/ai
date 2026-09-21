<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\ChatCompletionsClient;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
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
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ChatCompletionsClientTest extends TestCase
{
    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = new ChatCompletionsClient(new HttpTransport(new MockHttpClient(), 'http://localhost:8000'));

        $this->assertTrue($modelClient->supports(new CompletionsModel('gpt-4o')));
    }

    public function testStringPayloadThrowsException()
    {
        $modelClient = new ChatCompletionsClient(new HttpTransport(new MockHttpClient(), 'http://localhost:8000'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given');

        $modelClient->request(new CompletionsModel('gpt-4o'), 'string payload');
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:8000/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer sk-valid-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":1,"model":"gpt-4o","messages":[{"role":"user","content":"test message"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ChatCompletionsClient(new HttpTransport($httpClient, 'http://localhost:8000', 'sk-valid-api-key'));
        $modelClient->request(new CompletionsModel('gpt-4o'), ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'test message']]], ['temperature' => 1]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:8000/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer sk-valid-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":0.7,"model":"gpt-4o","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ChatCompletionsClient(new HttpTransport($httpClient, 'http://localhost:8000', 'sk-valid-api-key'));
        $modelClient->request(new CompletionsModel('gpt-4o'), ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'Hello']]], ['temperature' => 0.7]);
    }

    public function testItRequestsUsageForStreamedResponses()
    {
        $resultCallback = function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('http://localhost:8000/v1/chat/completions', $url);
            $this->assertSame('Authorization: Bearer sk-valid-api-key', $options['normalized_headers']['authorization'][0]);

            $this->assertSame('{"stream":true,"stream_options":{"include_usage":true},"model":"gpt-4o","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ChatCompletionsClient(new HttpTransport($httpClient, 'http://localhost:8000', 'sk-valid-api-key'));
        $modelClient->request(
            new CompletionsModel('gpt-4o'),
            ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'Hello']]],
            ['stream' => true],
        );
    }

    public function testItPreservesExplicitStreamOptionsForStreamedResponses()
    {
        $resultCallback = function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('http://localhost:8000/v1/chat/completions', $url);
            $this->assertSame('Authorization: Bearer sk-valid-api-key', $options['normalized_headers']['authorization'][0]);

            $this->assertSame('{"stream":true,"stream_options":{"foo":"bar"},"model":"gpt-4o","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ChatCompletionsClient(new HttpTransport($httpClient, 'http://localhost:8000', 'sk-valid-api-key'));
        $modelClient->request(
            new CompletionsModel('gpt-4o'),
            ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'Hello']]],
            ['stream' => true, 'stream_options' => ['foo' => 'bar']],
        );
    }

    #[TestWith(['https://api.inference.eu', 'https://api.inference.eu/v1/chat/completions'])]
    #[TestWith(['https://api.inference.com', 'https://api.inference.com/v1/chat/completions'])]
    #[TestWith(['https://api.inference.com/', 'https://api.inference.com/v1/chat/completions'])]
    #[TestWith(['https://api.inference.com///', 'https://api.inference.com/v1/chat/completions'])]
    public function testItUsesCorrectBaseUrl(string $baseUrl, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-valid-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ChatCompletionsClient(new HttpTransport($httpClient, $baseUrl, 'sk-valid-api-key'));
        $modelClient->request(new CompletionsModel('gpt-4o'), ['messages' => []]);
    }

    #[TestWith(['/custom/path', 'https://api.inference.com/custom/path'])]
    #[TestWith(['/v1/alternative/endpoint', 'https://api.inference.com/v1/alternative/endpoint'])]
    public function testsItUsesCorrectPathIfProvided(string $path, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-valid-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ChatCompletionsClient(new HttpTransport($httpClient, 'https://api.inference.com', 'sk-valid-api-key'), $path);
        $modelClient->request(new CompletionsModel('gpt-4o'), ['messages' => []]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): ResponseInterface {
            self::assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            self::assertJson($options['body']);
            self::assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ChatCompletionsClient(new HttpTransport($httpClient, 'http://localhost:8000', 'sk-valid-api-key'));
        $modelClient->request(new CompletionsModel('gpt-4o'), ['messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }

    public function testToolChoiceDefaultsToAutoWhenToolsAreProvided()
    {
        $capturedBody = $this->captureRequestBody(['tools' => $this->tools()]);

        $this->assertSame('auto', $capturedBody['tool_choice']);
    }

    public function testToolChoiceIsNotOverriddenWhenExplicitlySet()
    {
        $capturedBody = $this->captureRequestBody(['tools' => $this->tools(), 'tool_choice' => 'required']);

        $this->assertSame('required', $capturedBody['tool_choice']);
    }

    public function testToolChoiceIsNotSetWhenNoToolsAreProvided()
    {
        $capturedBody = $this->captureRequestBody([]);

        $this->assertArrayNotHasKey('tool_choice', $capturedBody);
    }

    public function testToolChoiceIsNotSetWhenToolsAreEmpty()
    {
        $capturedBody = $this->captureRequestBody(['tools' => []]);

        $this->assertArrayNotHasKey('tool_choice', $capturedBody);
    }

    public function testGatewayDefaultsCanBeDisabled()
    {
        $capturedBody = $this->captureRequestBody(['stream' => true, 'tools' => $this->tools()], false);

        $this->assertArrayNotHasKey('stream_options', $capturedBody);
        $this->assertArrayNotHasKey('tool_choice', $capturedBody);
    }

    public function testConvertTextResult()
    {
        $converter = self::client();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello world',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testConvertToolWithArgsCallResult()
    {
        $converter = self::client();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test_function',
                                    'arguments' => '{"arg1": "value1"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_123', $toolCalls[0]->getId());
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame(['arg1' => 'value1'], $toolCalls[0]->getArguments());
    }

    public function testConvertThrowsClearExceptionForMalformedToolCallArguments()
    {
        $converter = self::client();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"city":Berlin}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]);

        $this->expectException(MalformedToolCallException::class);
        $this->expectExceptionMessage('Model returned malformed JSON arguments for the "get_weather" tool: "Syntax error"');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertToolWithEmptyArgsCallResult()
    {
        $converter = self::client();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test_function',
                                    'arguments' => '',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_123', $toolCalls[0]->getId());
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame([], $toolCalls[0]->getArguments());
    }

    public function testConvertToolWithoutArgsCallResult()
    {
        $converter = self::client();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test_function',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_123', $toolCalls[0]->getId());
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame([], $toolCalls[0]->getArguments());
    }

    public function testConvertToolCallResultWithStopFinishReason()
    {
        $converter = new ChatCompletionsClient(new HttpTransport(new MockHttpClient(), 'http://localhost:8000'));
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test_function',
                                    'arguments' => '{"arg1": "value1"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_123', $toolCalls[0]->getId());
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame(['arg1' => 'value1'], $toolCalls[0]->getArguments());
        $this->assertSame('stop', $result->getMetadata()->get('finish_reason')->getRaw());
    }

    public function testConvertTextResultWithNullContent()
    {
        $converter = new ChatCompletionsClient(new HttpTransport(new MockHttpClient(), 'http://localhost:8000'));
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('', $result->getContent());
    }

    public function testConvertTextResultFromToolCallsFinishReasonWithContentOnly()
    {
        $converter = new ChatCompletionsClient(new HttpTransport(new MockHttpClient(), 'http://localhost:8000'));
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => '{"recipe":"Pasta Carbonara"}',
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('{"recipe":"Pasta Carbonara"}', $result->getContent());
        $this->assertSame('tool_calls', $result->getMetadata()->get('finish_reason')->getRaw());
    }

    public function testConvertThrowsOnToolCallsFinishReasonWithoutToolCallsOrContent()
    {
        $converter = new ChatCompletionsClient(new HttpTransport(new MockHttpClient(), 'http://localhost:8000'));
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported finish reason "tool_calls"');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertMultipleChoices()
    {
        $converter = self::client();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Choice 1',
                    ],
                    'finish_reason' => 'stop',
                ],
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Choice 2',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ChoiceResult::class, $result);
        $choices = $result->getContent();
        $this->assertCount(2, $choices);
        $this->assertSame('Choice 1', $choices[0]->getContent());
        $this->assertSame('Choice 2', $choices[1]->getContent());
    }

    public function testContentFilterException()
    {
        $converter = self::client();
        $httpResponse = $this->createMock(ResponseInterface::class);

        $httpResponse->expects($this->exactly(1))
            ->method('toArray')
            ->willReturnCallback(static function ($throw = true) {
                if ($throw) {
                    throw new class extends \Exception implements ClientExceptionInterface {
                        public function getResponse(): ResponseInterface
                        {
                            throw new RuntimeException('Not implemented');
                        }
                    };
                }

                return [
                    'error' => [
                        'code' => 'content_filter',
                        'message' => 'Content was filtered',
                    ],
                ];
            });

        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage('Content was filtered');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsAuthenticationExceptionOnInvalidApiKey()
    {
        $converter = self::client(new JsonMockResponse([
            'error' => [
                'message' => 'Invalid API key provided: sk-invalid',
            ],
        ], ['http_code' => 401]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key provided: sk-invalid');

        $converter->convert($converter->request(new CompletionsModel('gpt-4o'), []));
    }

    public function testThrowsExceptionWhenNoChoices()
    {
        $converter = self::client();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain choices');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceptionForUnsupportedFinishReason()
    {
        $converter = self::client();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Test content',
                    ],
                    'finish_reason' => 'unsupported_reason',
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported finish reason "unsupported_reason"');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    /**
     * @param array{message: string, code?: string|int} $error
     */
    #[DataProvider('provideContextOverflowErrors')]
    public function testThrowsExceedContextSizeExceptionOnContextOverflow(array $error)
    {
        $converter = self::client(new JsonMockResponse(['error' => $error], ['http_code' => 400]));

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage($error['message']);

        $converter->convert($converter->request(new CompletionsModel('gpt-4o'), []));
    }

    /**
     * @return iterable<string, array{array{message: string, code?: string|int}}>
     */
    public static function provideContextOverflowErrors(): iterable
    {
        yield 'error code' => [['message' => "This model's maximum context length is 128000 tokens.", 'code' => 'context_length_exceeded']];
        yield 'snake_case code in message' => [['message' => 'Error: context_length_exceeded', 'code' => 400]];
        yield 'spaced code variant in message' => [['message' => 'Context length exceeded for this request.']];
    }

    public function testThrowsExceedContextSizeExceptionOnFlatContextOverflowMessage()
    {
        $converter = self::client(new JsonMockResponse(['message' => 'Prompt contains 300019 tokens, too large for model with 262144 maximum context length'], ['http_code' => 400]));

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('maximum context length');

        $converter->convert($converter->request(new CompletionsModel('gpt-4o'), []));
    }

    public function testThrowsBadRequestExceptionOnBadRequestResponse()
    {
        $converter = self::client(new JsonMockResponse([
            'error' => [
                'message' => 'Bad Request: invalid parameters',
            ],
        ], ['http_code' => 400]));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request: invalid parameters');

        $converter->convert($converter->request(new CompletionsModel('gpt-4o'), []));
    }

    public function testThrowsBadRequestExceptionOnBadRequestResponseWithNoResponseBody()
    {
        $converter = self::client(new MockResponse('', ['http_code' => 400]));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request');

        $converter->convert($converter->request(new CompletionsModel('gpt-4o'), []));
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('provideUnhandledErrorStatuses')]
    public function testThrowsOnUnhandledErrorStatusBeforeStreaming(MockResponse $response, string $exception, string $message)
    {
        $converter = self::client($response);

        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        $converter->convert($converter->request(new CompletionsModel('gpt-4o'), [], ['stream' => true]), ['stream' => true]);
    }

    /**
     * @return iterable<string, array{MockResponse, class-string<\Throwable>, string}>
     */
    public static function provideUnhandledErrorStatuses(): iterable
    {
        yield 'not found' => [new MockResponse('404 page not found', ['http_code' => 404]), ModelNotFoundException::class, 'Not Found'];
        yield 'forbidden' => [new MockResponse('403 forbidden', ['http_code' => 403]), RuntimeException::class, 'Unexpected response code 403: "403 forbidden"'];
        yield 'unprocessable' => [new MockResponse('{"detail":"invalid"}', ['http_code' => 422]), RuntimeException::class, 'Unexpected response code 422: "{"detail":"invalid"}"'];
    }

    public function testThrowsServerExceptionOnServerErrorStatus()
    {
        $converter = self::client(new MockResponse('{"error":{"message":"service unavailable"}}', ['http_code' => 503]));

        try {
            $converter->convert($converter->request(new CompletionsModel('gpt-4o'), [], ['stream' => true]), ['stream' => true]);
            $this->fail('Expected a ServerException to be thrown.');
        } catch (ServerException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertStringContainsString('service unavailable', $e->getMessage());
        }
    }

    public function testThrowsDetailedErrorException()
    {
        $converter = self::client();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'error' => [
                'code' => 'invalid_request_error',
                'type' => 'invalid_request',
                'param' => 'model',
                'message' => 'The model `gpt-5` does not exist',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "invalid_request_error"-invalid_request (model): "The model `gpt-5` does not exist".');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testStreamingInterleavedReasoningContentAndToolCalls()
    {
        $converter = self::client();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['reasoning_content' => 'I need to check the weather']]]],
            ['choices' => [['index' => 0, 'delta' => ['reasoning_content' => 'Let me call the tool']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Let me check']]]],
            ['choices' => [['index' => 0, 'delta' => [
                'tool_calls' => [
                    [
                        'id' => 'call_1',
                        'type' => 'function',
                        'function' => [
                            'name' => 'get_weather',
                            'arguments' => '',
                        ],
                    ],
                ],
            ]]]],
            ['choices' => [['index' => 0, 'delta' => [
                'tool_calls' => [
                    [
                        'function' => [
                            'arguments' => '{"city":"Beijing"}',
                        ],
                    ],
                ],
            ]]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'tool_calls']]],
        ];

        $raw = new InMemoryRawResult([], $events, $this->httpResponseStub());
        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $thinkingDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingDelta));
        $this->assertCount(2, $thinkingDeltas);
        $this->assertSame('I need to check the weather', $thinkingDeltas[0]->getThinking());
        $this->assertSame('Let me call the tool', $thinkingDeltas[1]->getThinking());

        $thinkingCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes);
        $this->assertSame('I need to check the weatherLet me call the tool', $thinkingCompletes[0]->getThinking());

        $textDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof TextDelta));
        $this->assertCount(1, $textDeltas);
        $this->assertSame('Let me check', $textDeltas[0]->getText());

        $toolCallStarts = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolCallStart));
        $this->assertCount(1, $toolCallStarts);
        $this->assertSame('call_1', $toolCallStarts[0]->getId());
        $this->assertSame('get_weather', $toolCallStarts[0]->getName());

        $toolInputDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolInputDelta));
        $this->assertCount(1, $toolInputDeltas);
        $this->assertSame('call_1', $toolInputDeltas[0]->getId());
        $this->assertSame('get_weather', $toolInputDeltas[0]->getName());
        $this->assertSame('{"city":"Beijing"}', $toolInputDeltas[0]->getPartialJson());

        $toolCallCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolCallComplete));
        $this->assertCount(1, $toolCallCompletes);
        $completed = $toolCallCompletes[0]->getToolCalls();
        $this->assertCount(1, $completed);
        $this->assertSame('call_1', $completed[0]->getId());
        $this->assertSame('get_weather', $completed[0]->getName());
        $this->assertSame(['city' => 'Beijing'], $completed[0]->getArguments());
    }

    public function testStreamingToolCallsWithEmptyStringIdOnContinuationChunks()
    {
        // Some OpenAI-compatible providers (e.g. Alibaba Cloud Qwen / DashScope) send the tool-call
        // id ONLY on the first delta as a real value and then repeat it as an EMPTY STRING on every
        // continuation chunk (OpenAI itself omits the key entirely). `isset()` is true for "", so a
        // start must be keyed on a NON-EMPTY id — otherwise each continuation is misread as a new
        // tool-call start, the name is read from a delta that has none (Undefined array key "name"),
        // and the accumulated arguments are clobbered.
        $converter = self::client();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['id' => 'call_1', 'type' => 'function', 'index' => 0, 'function' => ['name' => 'get_weather', 'arguments' => '']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['id' => '', 'type' => 'function', 'index' => 0, 'function' => ['arguments' => '{"city":']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['id' => '', 'type' => 'function', 'index' => 0, 'function' => ['arguments' => '"Beijing"}']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'tool_calls']]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $toolCallStarts = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolCallStart));
        $this->assertCount(1, $toolCallStarts, 'an empty-string id on continuation chunks must not start a new tool call');
        $this->assertSame('call_1', $toolCallStarts[0]->getId());
        $this->assertSame('get_weather', $toolCallStarts[0]->getName());

        $toolCallCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolCallComplete));
        $this->assertCount(1, $toolCallCompletes);
        $completed = $toolCallCompletes[0]->getToolCalls();
        $this->assertCount(1, $completed);
        $this->assertSame('call_1', $completed[0]->getId());
        $this->assertSame('get_weather', $completed[0]->getName());
        $this->assertSame(['city' => 'Beijing'], $completed[0]->getArguments());
    }

    public function testStreamingParallelToolCallsWithProviderIndexOnSingleElementChunks()
    {
        // OpenAI-compatible streams often send one tool_calls[] entry per chunk; the real slot is
        // tool_calls[].index, not the PHP array key (always 0). Without index-based correlation,
        // parallel tool calls collapse into a single entry (symfony/ai#2193).
        $converter = self::client();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['index' => 0, 'id' => 'call_a', 'type' => 'function', 'function' => ['name' => 'get_weather', 'arguments' => '']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['index' => 0, 'function' => ['arguments' => '{"city":']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['index' => 0, 'function' => ['arguments' => '"Paris"}']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['index' => 1, 'id' => 'call_b', 'type' => 'function', 'function' => ['name' => 'get_time', 'arguments' => '']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['index' => 1, 'function' => ['arguments' => '{"tz":']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['index' => 1, 'function' => ['arguments' => '"CET"}']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'tool_calls']]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $toolCallStarts = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolCallStart));
        $this->assertCount(2, $toolCallStarts);
        $this->assertSame('call_a', $toolCallStarts[0]->getId());
        $this->assertSame('get_weather', $toolCallStarts[0]->getName());
        $this->assertSame('call_b', $toolCallStarts[1]->getId());
        $this->assertSame('get_time', $toolCallStarts[1]->getName());

        $toolInputDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolInputDelta));
        $this->assertCount(4, $toolInputDeltas);
        $this->assertSame('call_a', $toolInputDeltas[0]->getId());
        $this->assertSame('{"city":', $toolInputDeltas[0]->getPartialJson());
        $this->assertSame('call_a', $toolInputDeltas[1]->getId());
        $this->assertSame('"Paris"}', $toolInputDeltas[1]->getPartialJson());
        $this->assertSame('call_b', $toolInputDeltas[2]->getId());
        $this->assertSame('{"tz":', $toolInputDeltas[2]->getPartialJson());
        $this->assertSame('call_b', $toolInputDeltas[3]->getId());
        $this->assertSame('"CET"}', $toolInputDeltas[3]->getPartialJson());

        $toolCallCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolCallComplete));
        $this->assertCount(1, $toolCallCompletes);
        $completed = $toolCallCompletes[0]->getToolCalls();
        $this->assertCount(2, $completed);

        $byId = [];
        foreach ($completed as $toolCall) {
            $byId[$toolCall->getId()] = $toolCall;
        }

        $this->assertArrayHasKey('call_a', $byId);
        $this->assertSame('get_weather', $byId['call_a']->getName());
        $this->assertSame(['city' => 'Paris'], $byId['call_a']->getArguments());

        $this->assertArrayHasKey('call_b', $byId);
        $this->assertSame('get_time', $byId['call_b']->getName());
        $this->assertSame(['tz' => 'CET'], $byId['call_b']->getArguments());
    }

    public function testStreamingThrowsWhenFinishReasonIsMissing()
    {
        $converter = self::client();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hello, ']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'world!']]]],
            // stream cut off: no terminal chunk carrying a non-null finish_reason
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $this->expectException(IncompleteStreamException::class);
        $this->expectExceptionMessage('Completions stream ended before a finish reason was received.');

        iterator_to_array($streamResult->getContent());
    }

    public function testStreamingDoesNotThrowWhenFinishReasonIsPresent()
    {
        $converter = self::client();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hello, ']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'world!']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertInstanceOf(MetadataDelta::class, $chunks[2]);
        $this->assertSame('finish_reason', $chunks[2]->getKey());
        $this->assertSame(FinishReasonCase::STOP, $chunks[2]->getValue()->getCase());
    }

    public function testStreamingDoesNotThrowWithUsageOnlyFinalChunk()
    {
        $converter = self::client();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hi']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
            ['choices' => [], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $textDeltas = array_values(array_filter(iterator_to_array($streamResult->getContent()), static fn ($c) => $c instanceof TextDelta));
        $this->assertCount(1, $textDeltas);
        $this->assertSame('Hi', $textDeltas[0]->getText());
    }

    public function testStreamingYieldsTokenUsageWithModel()
    {
        $converter = self::client();

        $events = [
            ['model' => 'some-model', 'choices' => [['index' => 0, 'delta' => ['content' => 'Hi']]]],
            ['model' => 'some-model', 'choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
            ['model' => 'some-model', 'choices' => [], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $usages = array_values(array_filter(iterator_to_array($streamResult->getContent()), static fn ($c) => $c instanceof TokenUsage));

        $this->assertCount(1, $usages);
        $this->assertSame(1, $usages[0]->getPromptTokens());
        $this->assertSame(2, $usages[0]->getTotalTokens());
        $this->assertSame('some-model', $usages[0]->getModel());
    }

    public function testStreamingDoesNotThrowOnEmptyStream()
    {
        $converter = self::client();

        $streamResult = $converter->convert(new InMemoryRawResult([], [], $this->httpResponseStub()), ['stream' => true]);

        $this->assertSame([], iterator_to_array($streamResult->getContent()));
    }

    public function testStreamingThrowsOnTopLevelErrorEvent()
    {
        $converter = self::client();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'partial']]]],
            ['error' => ['message' => 'Invalid model', 'code' => 'invalid_request_error']],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream error: "Invalid model".');

        iterator_to_array($streamResult->getContent());
    }

    public function testStreamingThrowsServerExceptionOnServerErrorEvent()
    {
        $converter = self::client();

        $streamResult = $converter->convert(new InMemoryRawResult([], [[
            'error' => ['message' => 'Provider exploded mid-stream', 'code' => 'server_error'],
        ]], $this->httpResponseStub()), ['stream' => true]);

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error. Stream error: "Provider exploded mid-stream".');

        iterator_to_array($streamResult->getContent());
    }

    public function testStreamingThrowsRateLimitExceptionOnRateLimitEvent()
    {
        $converter = self::client();

        $streamResult = $converter->convert(new InMemoryRawResult([], [[
            'error' => ['message' => 'Too many requests', 'code' => 'rate_limit_error'],
        ]], $this->httpResponseStub()), ['stream' => true]);

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded. Stream error: "Too many requests".');

        iterator_to_array($streamResult->getContent());
    }

    #[DataProvider('provideStreamedFinishReasons')]
    public function testStreamingExposesFinishReasonAsMetadataDelta(string $rawFinishReason, FinishReasonCase $expectedCase)
    {
        $converter = self::client();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hello']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => $rawFinishReason]]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $metadataDeltas = array_values(array_filter(iterator_to_array($streamResult->getContent()), static fn (DeltaInterface $delta) => $delta instanceof MetadataDelta));

        $this->assertCount(1, $metadataDeltas);
        $this->assertSame('finish_reason', $metadataDeltas[0]->getKey());
        $this->assertSame($expectedCase, $metadataDeltas[0]->getValue()->getCase());
        $this->assertSame($rawFinishReason, $metadataDeltas[0]->getValue()->getRaw());
    }

    /**
     * @return iterable<string, array{string, FinishReasonCase}>
     */
    public static function provideStreamedFinishReasons(): iterable
    {
        yield 'stop' => ['stop', FinishReasonCase::STOP];
        yield 'length' => ['length', FinishReasonCase::LENGTH];
        yield 'content_filter' => ['content_filter', FinishReasonCase::CONTENT_FILTER];
    }

    public function testStreamingEmitsFinishReasonAfterTheToolCallItTerminates()
    {
        $converter = self::client();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [['id' => 'call_1', 'function' => ['name' => 'get_weather', 'arguments' => '{}']]]]]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'tool_calls']]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[1]);
        $this->assertInstanceOf(MetadataDelta::class, $chunks[2]);
        $this->assertSame(FinishReasonCase::TOOL_CALL, $chunks[2]->getValue()->getCase());
    }

    public function testStreamingCompletesToolCallsWithStopFinishReason()
    {
        $converter = new ChatCompletionsClient(new HttpTransport(new MockHttpClient(), 'http://localhost:8000'));

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [['id' => 'call_1', 'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Berlin"}']]]]]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[1]);

        $completed = $chunks[1]->getToolCalls();
        $this->assertCount(1, $completed);
        $this->assertSame('call_1', $completed[0]->getId());
        $this->assertSame('get_weather', $completed[0]->getName());
        $this->assertSame(['city' => 'Berlin'], $completed[0]->getArguments());

        $this->assertInstanceOf(MetadataDelta::class, $chunks[2]);
        $this->assertSame(FinishReasonCase::STOP, $chunks[2]->getValue()->getCase());
        $this->assertSame('stop', $chunks[2]->getValue()->getRaw());
    }

    public function testStreamingSurfacesToolCallArgumentsTruncatedByALengthFinishReason()
    {
        $converter = new ChatCompletionsClient(new HttpTransport(new MockHttpClient(), 'http://localhost:8000'));

        // The model ran into the token limit mid-arguments, so the accumulated JSON is a fragment.
        // Completing the tool call on any finish reason surfaces that as a malformed tool call
        // instead of silently dropping the call and leaving the agent without a result.
        $events = [
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [['id' => 'call_1', 'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Ber']]]]]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'length']]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $this->expectException(MalformedToolCallException::class);
        $this->expectExceptionMessage('Model returned malformed JSON arguments for the "get_weather" tool');

        iterator_to_array($streamResult->getContent());
    }

    public function testStreamingEmitsFinishReasonAfterTheContentOfTheChunkThatCarriedIt()
    {
        $converter = self::client();

        // Mistral and other compatible providers bundle the final content token with the finish_reason.
        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hel']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'lo!'], 'finish_reason' => 'stop']]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame('lo!', $chunks[1]->getText());
        $this->assertInstanceOf(MetadataDelta::class, $chunks[2]);
    }

    public function testStreamingPromotesFinishReasonToResultMetadata()
    {
        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hello']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'length']]],
        ];

        $deferredResult = new DeferredResult(
            self::client(),
            new InMemoryRawResult([], $events, $this->httpResponseStub()),
            ['stream' => true],
        );

        $chunks = iterator_to_array($deferredResult->asStream());

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertTrue($deferredResult->getMetadata()->has('finish_reason'));

        $finishReason = $deferredResult->getMetadata()->get('finish_reason');
        $this->assertInstanceOf(FinishReason::class, $finishReason);
        $this->assertTrue($finishReason->is(FinishReasonCase::LENGTH));
        $this->assertSame('length', $finishReason->getRaw());
    }

    public function testStreamingEmitsFinishReasonOnlyOnceWithUsageOnlyFinalChunk()
    {
        $converter = self::client();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hi']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
            ['choices' => [], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);
        $chunks = iterator_to_array($streamResult->getContent());

        $metadataDeltas = array_values(array_filter($chunks, static fn (DeltaInterface $delta) => $delta instanceof MetadataDelta));
        $this->assertCount(1, $metadataDeltas);
        $this->assertSame(FinishReasonCase::STOP, $metadataDeltas[0]->getValue()->getCase());
    }

    public function testBufferedResultCarriesFinishReasonMetadata()
    {
        $converter = self::client();

        $data = [
            'choices' => [
                ['index' => 0, 'finish_reason' => 'length', 'message' => ['role' => 'assistant', 'content' => 'Truncated']],
            ],
        ];

        $result = $converter->convert(new InMemoryRawResult($data, [], $this->httpResponseStub()));

        $finishReason = $result->getMetadata()->get('finish_reason');
        $this->assertInstanceOf(FinishReason::class, $finishReason);
        $this->assertTrue($finishReason->is(FinishReasonCase::LENGTH));
        $this->assertSame('length', $finishReason->getRaw());
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function captureRequestBody(array $options, bool $applyGatewayDefaults = true): array
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function ($method, $url, $httpOptions) use (&$capturedBody) {
            $capturedBody = json_decode($httpOptions['body'], true);

            return new MockResponse(json_encode([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hello']]],
            ]));
        });

        $modelClient = new ChatCompletionsClient(new HttpTransport($httpClient, 'http://localhost:8000', 'sk-valid-api-key'), applyGatewayDefaults: $applyGatewayDefaults);
        $modelClient->request(new CompletionsModel('gpt-4o'), [
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ], $options);

        $this->assertIsArray($capturedBody);

        return $capturedBody;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tools(): array
    {
        return [
            ['type' => 'function', 'function' => ['name' => 'tool_a', 'description' => 'First tool']],
        ];
    }

    private function httpResponseStub(): object
    {
        return new class {
            public function getStatusCode(): int
            {
                return 200;
            }
        };
    }

    private static function client(?MockResponse $response = null): ChatCompletionsClient
    {
        return new ChatCompletionsClient(new HttpTransport(new MockHttpClient($response), 'http://localhost:8000'));
    }
}
