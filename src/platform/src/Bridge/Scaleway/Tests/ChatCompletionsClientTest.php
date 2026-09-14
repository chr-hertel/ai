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
use Symfony\AI\Platform\Bridge\Scaleway\ChatCompletionsClient;
use Symfony\AI\Platform\Bridge\Scaleway\Scaleway;
use Symfony\AI\Platform\Bridge\Scaleway\ScalewayResponses;
use Symfony\AI\Platform\Bridge\Scaleway\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class ChatCompletionsClientTest extends TestCase
{
    public function testItSupportsScalewayModel()
    {
        $converter = self::createClient();

        $this->assertTrue($converter->supports(new Scaleway('deepseek-r1-distill-llama-70b')));
    }

    public function testItDoesNotSupportResponsesModel()
    {
        $converter = self::createClient();

        $this->assertFalse($converter->supports(new ScalewayResponses('gpt-oss-120b')));
    }

    public function testConvertTextResult()
    {
        $converter = self::createClient();
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
        $converter = self::createClient();
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

    public function testConvertToolWithEmptyArgsCallResult()
    {
        $converter = self::createClient();
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
        $converter = self::createClient();
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

    public function testConvertMultipleChoices()
    {
        $converter = self::createClient();
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
        $converter = self::createClient();
        $httpResponse = $this->createMock(ResponseInterface::class);

        $httpResponse->expects($this->exactly(2))
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

    public function testThrowsExceptionOnApiError()
    {
        $converter = self::createClient();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'error' => [
                'type' => 'invalid_request_error',
                'message' => 'Invalid model specified',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "invalid_request_error": "Invalid model specified"');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $converter = self::createClient();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'context_length_exceeded',
                'message' => "This model's maximum context length is 128000 tokens.",
            ],
        ]);

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('maximum context length');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceedContextSizeExceptionOnMaxModelLenBadRequest()
    {
        $converter = self::createClient(new MockHttpClient(new JsonMockResponse([
            'error' => [
                'type' => 'BadRequestError',
                'message' => 'Please reduce the length of the messages or increase max_model_len.',
            ],
        ], ['http_code' => 400])));

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('increase max_model_len');

        $converter->convert($converter->request(new Scaleway('deepseek-r1-distill-llama-70b'), ['messages' => []]));
    }

    public function testThrowsExceptionWhenNoChoices()
    {
        $converter = self::createClient();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain choices');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceptionForUnsupportedFinishReason()
    {
        $converter = self::createClient();
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

    public function testThrowsServerExceptionOnServerErrorStatusBeforeStreaming()
    {
        $converter = self::createClient(new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 500])));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        $converter->convert($converter->request(new Scaleway('deepseek-r1-distill-llama-70b'), ['messages' => []], ['stream' => true]), ['stream' => true]);
    }

    private static function createClient(MockHttpClient $httpClient = new MockHttpClient()): ChatCompletionsClient
    {
        return new ChatCompletionsClient(new HttpTransport($httpClient, 'https://api.scaleway.ai', 'scaleway-api-key'), modelClass: Scaleway::class, applyGatewayDefaults: false);
    }
}
