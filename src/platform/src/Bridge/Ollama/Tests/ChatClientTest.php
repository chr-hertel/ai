<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\ChatClient;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ChatClientTest extends TestCase
{
    public function testSupportsLlamaModel()
    {
        $converter = new ChatClient(new MockHttpClient());

        $this->assertTrue($converter->supports(new Ollama('llama3.2', [Capability::INPUT_MESSAGES])));
        $this->assertFalse($converter->supports(new Model('any-model')));
    }

    public function testOutputStructureIsSupported()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $json = $this->decodeRequestJson($options);

            $this->assertFalse($json['stream']);
            $this->assertSame([
                'type' => 'object',
                'properties' => [
                    'age' => ['type' => 'integer'],
                    'available' => ['type' => 'boolean'],
                ],
                'required' => ['age', 'available'],
                'additionalProperties' => false,
            ], $json['format']);
            $this->assertArrayNotHasKey('response_format', $json);
            $this->assertArrayNotHasKey('options', $json);

            return new JsonMockResponse([
                'model' => 'foo',
                'response' => [
                    'age' => 22,
                    'available' => true,
                ],
                'done' => true,
            ]);
        }, 'http://127.0.0.1:1234');

        $client = new ChatClient($httpClient);
        $response = $client->request(new Ollama('llama3.2', [
            Capability::INPUT_MESSAGES,
            Capability::TOOL_CALLING,
        ]), [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Ollama is 22 years old and is busy saving the world. Respond using JSON',
                ],
            ],
            'model' => 'llama3.2',
        ], [
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'clock',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'age' => ['type' => 'integer'],
                            'available' => ['type' => 'boolean'],
                        ],
                        'required' => ['age', 'available'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertSame([
            'model' => 'foo',
            'response' => [
                'age' => 22,
                'available' => true,
            ],
            'done' => true,
        ], $response->getData());
    }

    public function testChatRequestMovesNonTopLevelOptionsIntoNestedOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('POST', $method);
            $this->assertSame('http://127.0.0.1:1234/api/chat', $url);

            $json = $this->decodeRequestJson($options);

            $this->assertTrue($json['stream']);
            $this->assertFalse($json['think']);

            $this->assertArrayHasKey('options', $json);
            $this->assertIsArray($json['options']);
            $this->assertSame(0.2, $json['options']['temperature']);
            $this->assertSame(64, $json['options']['num_predict']);

            $this->assertArrayNotHasKey('temperature', $json);
            $this->assertArrayNotHasKey('num_predict', $json);

            return new JsonMockResponse([
                'model' => 'llama3.2',
                'message' => ['role' => 'assistant', 'content' => 'ok'],
                'done' => true,
            ]);
        }, 'http://127.0.0.1:1234');

        $client = new ChatClient($httpClient);

        $client->request(
            new Ollama('llama3.2', [Capability::INPUT_MESSAGES]),
            [
                'model' => 'llama3.2',
                'messages' => [
                    ['role' => 'user', 'content' => 'hi'],
                ],
            ],
            [
                'stream' => true,
                'think' => false,
                'temperature' => 0.2,
                'num_predict' => 64,
            ]
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testChatRequestMergesExplicitNestedOptionsWithFlatOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $json = $this->decodeRequestJson($options);

            $this->assertSame([
                'num_predict' => 64,
                'num_ctx' => 1024,
                'temperature' => 0.2,
            ], $json['options']);

            return new JsonMockResponse([
                'model' => 'llama3.2',
                'message' => ['role' => 'assistant', 'content' => 'ok'],
                'done' => true,
            ]);
        }, 'http://127.0.0.1:1234');

        $client = new ChatClient($httpClient);

        $client->request(
            new Ollama('llama3.2', [Capability::INPUT_MESSAGES]),
            [
                'model' => 'llama3.2',
                'messages' => [
                    ['role' => 'user', 'content' => 'hi'],
                ],
            ],
            [
                'temperature' => 0.2,
                'options' => [
                    'num_predict' => 64,
                    'num_ctx' => 1024,
                ],
            ]
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testChatRequestKeepsStructuredOutputFormatOnTopLevel()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $json = $this->decodeRequestJson($options);

            $this->assertArrayHasKey('format', $json);
            $this->assertArrayNotHasKey('format', $json['options'] ?? []);

            return new JsonMockResponse([
                'model' => 'llama3.2',
                'message' => ['role' => 'assistant', 'content' => '{"ok":true}'],
                'done' => true,
            ]);
        }, 'http://127.0.0.1:1234');

        $client = new ChatClient($httpClient);

        $client->request(
            new Ollama('llama3.2', [Capability::INPUT_MESSAGES]),
            [
                'model' => 'llama3.2',
                'messages' => [
                    ['role' => 'user', 'content' => 'respond in json'],
                ],
            ],
            [
                PlatformSubscriber::RESPONSE_FORMAT => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'x',
                        'schema' => ['type' => 'object'],
                    ],
                ],
                'temperature' => 0.2,
            ]
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new JsonMockResponse([
                'model' => 'llama3.2',
                'message' => ['role' => 'assistant', 'content' => 'ok'],
                'done' => true,
            ]);
        }, 'http://127.0.0.1:1234');

        $client = new ChatClient($httpClient);
        $client->request(new Ollama('llama3.2', [Capability::INPUT_MESSAGES]), ['model' => 'llama3.2', 'messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }

    public function testRequestThrowsExceptionForStringPayload()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given to "Symfony\AI\Platform\Bridge\Ollama\ChatClient".');

        (new ChatClient(new MockHttpClient()))->request(new Ollama('llama3.2', [Capability::INPUT_MESSAGES]), 'hi');
    }

    public function testConvertTextResponse()
    {
        $converter = new ChatClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult([
            'message' => [
                'content' => 'Hello world',
            ],
        ]);

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());

        $this->assertNotInstanceOf(StreamResult::class, $converter->convert($rawResult, ['stream' => false]));
    }

    public function testConvertToolCallResponse()
    {
        $converter = new ChatClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult([
            'message' => [
                'content' => 'This content will be ignored because tool_calls are present',
                'tool_calls' => [
                    [
                        'function' => [
                            'name' => 'test_function',
                            'arguments' => ['arg1' => 'value1'],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('0', $toolCalls[0]->getId()); // ID is the array index as a string
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame(['arg1' => 'value1'], $toolCalls[0]->getArguments());
    }

    public function testConvertMultipleToolCallsResponse()
    {
        $converter = new ChatClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult([
            'message' => [
                'content' => 'This content will be ignored because tool_calls are present',
                'tool_calls' => [
                    [
                        'function' => [
                            'name' => 'function1',
                            'arguments' => ['param1' => 'value1'],
                        ],
                    ],
                    [
                        'function' => [
                            'name' => 'function2',
                            'arguments' => ['param2' => 'value2'],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(2, $toolCalls);

        $this->assertSame('0', $toolCalls[0]->getId());
        $this->assertSame('function1', $toolCalls[0]->getName());
        $this->assertSame(['param1' => 'value1'], $toolCalls[0]->getArguments());

        $this->assertSame('1', $toolCalls[1]->getId());
        $this->assertSame('function2', $toolCalls[1]->getName());
        $this->assertSame(['param2' => 'value2'], $toolCalls[1]->getArguments());
    }

    public function testThrowsExceptionWhenNoMessage()
    {
        $converter = new ChatClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain message');

        $converter->convert($rawResult);
    }

    public function testThrowsExceptionWhenNoContent()
    {
        $converter = new ChatClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult([
            'message' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Message does not contain content');

        $converter->convert($rawResult);
    }

    public function testConvertStreamingResponse()
    {
        $converter = new ChatClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult(dataStream: $this->generateConvertStreamingStream());

        $result = $converter->convert($rawResult, options: ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = iterator_to_array($result->getContent());

        $this->assertCount(4, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame(' world!', $chunks[1]->getText());
        $this->assertInstanceOf(TokenUsageInterface::class, $chunks[2]);
        $this->assertSame(42, $chunks[2]->getPromptTokens());
        $this->assertSame(17, $chunks[2]->getCompletionTokens());
        $this->assertInstanceOf(MetadataDelta::class, $chunks[3]);
        $this->assertSame('finish_reason', $chunks[3]->getKey());
        $this->assertTrue($chunks[3]->getValue()->is(FinishReasonCase::STOP));
    }

    public function testConvertThinkingStreamingResponse()
    {
        $converter = new ChatClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult(dataStream: $this->generateConvertThinkingStreamingStream());

        $result = $converter->convert($rawResult, options: ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = iterator_to_array($result->getContent());

        $this->assertCount(6, $chunks);
        $this->assertInstanceOf(ThinkingDelta::class, $chunks[0]);
        $this->assertSame('Thinking', $chunks[0]->getThinking());
        $this->assertInstanceOf(ThinkingDelta::class, $chunks[1]);
        $this->assertSame(' hard', $chunks[1]->getThinking());
        $this->assertInstanceOf(TextDelta::class, $chunks[2]);
        $this->assertSame('Hello', $chunks[2]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[3]);
        $this->assertSame(' world!', $chunks[3]->getText());
        $this->assertInstanceOf(TokenUsageInterface::class, $chunks[4]);
        $this->assertSame(42, $chunks[4]->getPromptTokens());
        $this->assertSame(17, $chunks[4]->getCompletionTokens());
        $this->assertInstanceOf(MetadataDelta::class, $chunks[5]);
        $this->assertTrue($chunks[5]->getValue()->is(FinishReasonCase::STOP));
    }

    public function testItPromotesTokenUsageMetadataFromStreamingResponse()
    {
        $deferredResult = new DeferredResult(
            new ChatClient(new MockHttpClient()),
            new InMemoryRawResult(dataStream: $this->generateConvertStreamingStream()),
            ['stream' => true],
        );

        iterator_to_array($deferredResult->asStream());

        $this->assertInstanceOf(TokenUsageInterface::class, $tokenUsage = $deferredResult->getMetadata()->get('token_usage'));
        $this->assertSame(42, $tokenUsage->getPromptTokens());
        $this->assertSame(17, $tokenUsage->getCompletionTokens());
        $this->assertNull($tokenUsage->getTotalTokens());
        $this->assertSame('deepseek-r1:latest', $tokenUsage->getModel());
    }

    public function testConvertStreamingToolCallResponse()
    {
        $converter = new ChatClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult(dataStream: $this->generateConvertToolCallStreamingStream());

        $result = $converter->convert($rawResult, options: ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = iterator_to_array($result->getContent());

        $this->assertCount(4, $chunks);
        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertSame('0', $chunks[0]->getId());
        $this->assertSame('clock', $chunks[0]->getName());
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[1]);
        $toolCalls = $chunks[1]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('clock', $toolCalls[0]->getName());
        $this->assertSame(['timezone' => 'UTC'], $toolCalls[0]->getArguments());
        $this->assertInstanceOf(TokenUsageInterface::class, $chunks[2]);
        $this->assertSame(11, $chunks[2]->getPromptTokens());
        $this->assertSame(4, $chunks[2]->getCompletionTokens());
        $this->assertInstanceOf(MetadataDelta::class, $chunks[3]);
        $this->assertTrue($chunks[3]->getValue()->is(FinishReasonCase::STOP));
    }

    public function testConvertStreamingToolCallsArrivingInSeparateChunksKeepDistinctIds()
    {
        $converter = new ChatClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult(dataStream: (static function (): iterable {
            yield ['model' => 'llama3.2', 'message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'clock', 'arguments' => ['timezone' => 'UTC']]]]], 'done' => false];
            yield ['model' => 'llama3.2', 'message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'weather', 'arguments' => ['city' => 'Berlin']]]]], 'done' => false];
            yield ['model' => 'llama3.2', 'message' => ['role' => 'assistant', 'content' => ''], 'done' => true, 'done_reason' => 'stop'];
        })());

        $chunks = iterator_to_array($converter->convert($rawResult, options: ['stream' => true])->getContent());

        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertSame('0', $chunks[0]->getId());
        $this->assertInstanceOf(ToolCallStart::class, $chunks[1]);
        $this->assertSame('1', $chunks[1]->getId());
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[2]);

        $toolCalls = $chunks[2]->getToolCalls();
        $this->assertCount(2, $toolCalls);
        $this->assertSame('0', $toolCalls[0]->getId());
        $this->assertSame('clock', $toolCalls[0]->getName());
        $this->assertSame('1', $toolCalls[1]->getId());
        $this->assertSame('weather', $toolCalls[1]->getName());
    }

    public function testConvertStreamingThrowsWhenDoneIsMissing()
    {
        $converter = new ChatClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult(dataStream: (static function (): iterable {
            yield ['model' => 'llama3.2', 'message' => ['role' => 'assistant', 'content' => 'Hello'], 'done' => false];
            yield ['model' => 'llama3.2', 'message' => ['role' => 'assistant', 'content' => ' world'], 'done' => false];
            // stream cut off: no object with done => true
        })());

        $result = $converter->convert($rawResult, options: ['stream' => true]);

        $this->expectException(IncompleteStreamException::class);
        $this->expectExceptionMessage('Ollama stream ended before a "done" message.');

        iterator_to_array($result->getContent());
    }

    public function testConvertStreamingThrowsOnErrorObject()
    {
        $converter = new ChatClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult(dataStream: (static function (): iterable {
            yield ['model' => 'llama3.2', 'message' => ['role' => 'assistant', 'content' => 'Hello'], 'done' => false];
            yield ['error' => 'model runner crashed'];
        })());

        $result = $converter->convert($rawResult, options: ['stream' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ollama stream error: "model runner crashed".');

        iterator_to_array($result->getContent());
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function generateConvertStreamingStream(): iterable
    {
        yield ['model' => 'deepseek-r1:latest', 'created_at' => '2025-10-29T17:15:49.631700779Z', 'message' => ['role' => 'assistant', 'content' => 'Hello'], 'done' => false];
        yield ['model' => 'deepseek-r1:latest', 'created_at' => '2025-10-29T17:15:49.905924913Z', 'message' => ['role' => 'assistant', 'content' => ' world!'], 'done' => true,
            'done_reason' => 'stop', 'total_duration' => 100, 'load_duration' => 10, 'prompt_eval_count' => 42, 'prompt_eval_duration' => 30, 'eval_count' => 17, 'eval_duration' => 60];
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function generateConvertThinkingStreamingStream(): iterable
    {
        yield ['model' => 'deepseek-r1:latest', 'created_at' => '2025-10-29T17:15:49.631700779Z', 'message' => ['role' => 'assistant', 'content' => '', 'thinking' => 'Thinking'], 'done' => false];
        yield ['model' => 'deepseek-r1:latest', 'created_at' => '2025-10-29T17:15:49.905924913Z', 'message' => ['role' => 'assistant', 'content' => '', 'thinking' => ' hard'], 'done' => false];
        yield ['model' => 'deepseek-r1:latest', 'created_at' => '2025-10-29T17:15:50.14497475Z', 'message' => ['role' => 'assistant', 'content' => 'Hello'], 'done' => false];
        yield ['model' => 'deepseek-r1:latest', 'created_at' => '2025-10-29T17:15:50.367912083Z', 'message' => ['role' => 'assistant', 'content' => ' world!'], 'done' => true,
            'done_reason' => 'stop', 'total_duration' => 100, 'load_duration' => 10, 'prompt_eval_count' => 42, 'prompt_eval_duration' => 30, 'eval_count' => 17, 'eval_duration' => 60];
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function generateConvertToolCallStreamingStream(): iterable
    {
        yield ['model' => 'llama3.2', 'created_at' => '2026-03-16T10:57:17.936041Z', 'message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'clock', 'arguments' => ['timezone' => 'UTC']]]]], 'done' => false];
        yield ['model' => 'llama3.2', 'created_at' => '2026-03-16T10:57:18.330845Z', 'message' => ['role' => 'assistant', 'content' => ''], 'done' => true,
            'done_reason' => 'stop', 'total_duration' => 100, 'load_duration' => 10, 'prompt_eval_count' => 11, 'prompt_eval_duration' => 30, 'eval_count' => 4, 'eval_duration' => 60];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function decodeRequestJson(array $options): array
    {
        $this->assertArrayHasKey('body', $options, 'Expected "body" in MockHttpClient options.');
        $this->assertIsString($options['body']);

        $data = json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR);

        $this->assertIsArray($data);

        return $data;
    }
}
