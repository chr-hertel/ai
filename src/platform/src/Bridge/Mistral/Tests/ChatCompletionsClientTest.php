<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Bridge\Mistral\ChatCompletionsClient;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ChatCompletionsClientTest extends TestCase
{
    public function testItSupportsMistralModel()
    {
        $converter = self::client();

        $this->assertTrue($converter->supports(new Mistral('mistral-large-latest')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $client = self::client([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mistral.ai/v1/chat/completions', $url);
            $this->assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);
            $this->assertSame('{"messages":[{"role":"user","content":"Hello"}],"model":"mistral-large-latest"}', $options['body']);

            return new MockResponse();
        }]);

        $client->request(new Mistral('mistral-large-latest'), ['messages' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $client = self::client([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        }]);

        $client->request(new Mistral('mistral-large-latest'), ['messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }

    /**
     * Not a cassette: provoking this for real means overflowing the smallest available Mistral
     * context window, so the recorded request body would be a ~640 KB prompt of filler text.
     */
    public function testConvertThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('maximum context length');

        $converter = self::client(new JsonMockResponse([
            'message' => 'Prompt contains 300019 tokens and 0 draft tokens, too large for model with 262144 maximum context length',
        ], ['http_code' => 400]));

        $converter->convert($converter->request(new Mistral('mistral-large-latest'), ['messages' => []]));
    }

    /**
     * Not a cassette: a provider cannot be asked for a 500 on demand. The assertion is on our own
     * status handling anyway - the body is irrelevant - so a mock is the honest tool here.
     */
    public function testThrowsServerExceptionOnServerErrorStatusBeforeStreaming()
    {
        $converter = self::client(new JsonMockResponse(['error' => 'Service Unavailable'], ['http_code' => 500]));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        $converter->convert($converter->request(new Mistral('mistral-large-latest'), ['messages' => []], ['stream' => true]), ['stream' => true]);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testThrowsOnUnhandledErrorStatus(bool $stream)
    {
        $converter = self::client(new MockResponse('{"message":"Forbidden"}', ['http_code' => 403]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 403: "{"message":"Forbidden"}"');

        $converter->convert($converter->request(new Mistral('mistral-large-latest'), ['messages' => []], ['stream' => $stream]), ['stream' => $stream]);
    }

    /**
     * With `reasoning_effort: high`, Mistral streams the thinking trace inside an array-shaped
     * `delta.content` (a `thinking` chunk), then a transition chunk carrying the closing thinking
     * plus the first text chunk, then plain-string `content` for the answer.
     *
     * @see https://docs.mistral.ai/capabilities/reasoning/
     */
    public function testStreamingReasoningEffortHighEmitsThinkingThenTextDeltas()
    {
        $converter = self::client();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => [
                ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'Let me']]],
            ]], 'finish_reason' => null]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => [
                ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => ' think.']]],
                ['type' => 'text', 'text' => 'The answer'],
            ]], 'finish_reason' => null]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => ' is 391.'], 'finish_reason' => null]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = iterator_to_array($streamResult->getContent(), false);

        $thinkingDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingDelta));
        $this->assertCount(2, $thinkingDeltas);
        $this->assertSame('Let me', $thinkingDeltas[0]->getThinking());
        $this->assertSame(' think.', $thinkingDeltas[1]->getThinking());

        $thinkingCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes);
        $this->assertSame('Let me think.', $thinkingCompletes[0]->getThinking());

        $textDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof TextDelta));
        $this->assertCount(2, $textDeltas);
        $this->assertSame('The answer', $textDeltas[0]->getText());
        $this->assertSame(' is 391.', $textDeltas[1]->getText());

        $metadataDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof MetadataDelta));
        $this->assertCount(1, $metadataDeltas);
        $this->assertSame('finish_reason', $metadataDeltas[0]->getKey());
        $this->assertSame(FinishReasonCase::STOP, $metadataDeltas[0]->getValue()->getCase());

        // ThinkingComplete must precede the first TextDelta.
        $thinkingCompleteIndex = array_search($thinkingCompletes[0], $chunks, true);
        $firstTextDeltaIndex = array_search($textDeltas[0], $chunks, true);
        $this->assertLessThan($firstTextDeltaIndex, $thinkingCompleteIndex);
    }

    /**
     * Regression guard for the OpenAI-compatible path: with `reasoning_effort: none`, `delta.content`
     * is a plain string and must yield only text deltas.
     */
    public function testStreamingReasoningEffortNoneEmitsOnlyTextDeltas()
    {
        $converter = self::client();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hello, '], 'finish_reason' => null]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'world!'], 'finish_reason' => 'stop']]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $chunks = iterator_to_array($streamResult->getContent(), false);

        $this->assertCount(0, array_filter($chunks, static fn ($c) => $c instanceof ThinkingDelta));
        $this->assertCount(0, array_filter($chunks, static fn ($c) => $c instanceof ThinkingComplete));

        $textDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof TextDelta));
        $this->assertCount(2, $textDeltas);
        $this->assertSame('Hello, ', $textDeltas[0]->getText());
        $this->assertSame('world!', $textDeltas[1]->getText());

        $metadataDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof MetadataDelta));
        $this->assertCount(1, $metadataDeltas);
        $this->assertSame(FinishReasonCase::STOP, $metadataDeltas[0]->getValue()->getCase());
    }

    /**
     * With `reasoning_effort: high`, the buffered `message.content` is an array of thinking/text
     * chunks and must split into a {@see MultiPartResult} of {@see ThinkingResult} + {@see TextResult}.
     */
    public function testBufferedReasoningEffortHighSplitsThinkingAndText()
    {
        $converter = self::client();

        $data = [
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => [
                            ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'Let me think.']]],
                            ['type' => 'text', 'text' => 'The answer is 391.'],
                        ],
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ];

        $result = $converter->convert(new InMemoryRawResult($data, [], $this->httpResponseStub()));

        $this->assertInstanceOf(MultiPartResult::class, $result);

        $parts = $result->getContent();
        $this->assertCount(2, $parts);
        $this->assertInstanceOf(ThinkingResult::class, $parts[0]);
        $this->assertSame('Let me think.', $parts[0]->getContent());
        $this->assertInstanceOf(TextResult::class, $parts[1]);
        $this->assertSame('The answer is 391.', $parts[1]->getContent());
        $this->assertSame('The answer is 391.', $result->asText());

        $this->assertTrue($result->getMetadata()->get('finish_reason')->is(FinishReasonCase::STOP));
    }

    /**
     * A buffered response with a single thinking chunk (no text) must collapse to a lone
     * {@see ThinkingResult} rather than a {@see MultiPartResult}.
     */
    public function testBufferedReasoningEffortHighWithThinkingOnlyReturnsThinkingResult()
    {
        $converter = self::client();

        $data = [
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => [
                            ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'Still thinking...']]],
                        ],
                    ],
                    'finish_reason' => 'length',
                ],
            ],
        ];

        $result = $converter->convert(new InMemoryRawResult($data, [], $this->httpResponseStub()));

        $this->assertInstanceOf(ThinkingResult::class, $result);
        $this->assertSame('Still thinking...', $result->getContent());
        $this->assertTrue($result->getMetadata()->get('finish_reason')->is(FinishReasonCase::LENGTH));
    }

    /**
     * Regression guard for the OpenAI-compatible path: a plain-string buffered `message.content`
     * must still produce a {@see TextResult}.
     */
    public function testBufferedReasoningEffortNoneReturnsTextResult()
    {
        $converter = self::client();

        $data = [
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'Hello world'],
                    'finish_reason' => 'stop',
                ],
            ],
        ];

        $result = $converter->convert(new InMemoryRawResult($data, [], $this->httpResponseStub()));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    private function httpResponseStub(): ResponseInterface
    {
        return (new MockHttpClient(new JsonMockResponse([], ['http_code' => 200])))
            ->request('POST', 'https://api.mistral.ai/v1/chat/completions');
    }

    /**
     * @param callable|MockResponse|list<callable>|null $response
     */
    private static function client(callable|MockResponse|array|null $response = null): ChatCompletionsClient
    {
        return new ChatCompletionsClient(new HttpTransport(new MockHttpClient($response), 'https://api.mistral.ai', 'test-api-key'), modelClass: Mistral::class, applyGatewayDefaults: false);
    }
}
