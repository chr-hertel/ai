<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AmazeeAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\AmazeeAi\ChatCompletionsClient;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ChatCompletionsClientTest extends TestCase
{
    public function testSupportsCompletionsModel()
    {
        $converter = self::createClient();

        $this->assertTrue($converter->supports(new CompletionsModel('test', [Capability::INPUT_MESSAGES])));
    }

    public function testDoesNotSupportEmbeddingsModel()
    {
        $converter = self::createClient();

        $this->assertFalse($converter->supports(new EmbeddingsModel('test', [Capability::EMBEDDINGS])));
    }

    public function testConvertStopFinishReason()
    {
        $converter = self::createClient();
        $result = new RawHttpResult($this->createResponse([
            'choices' => [
                [
                    'finish_reason' => 'stop',
                    'message' => ['content' => 'Hello world'],
                ],
            ],
        ]));

        $converted = $converter->convert($result);

        $this->assertInstanceOf(TextResult::class, $converted);
        $this->assertSame('Hello world', $converted->getContent());
    }

    public function testConvertToolCallsFinishReasonWithToolCalls()
    {
        $converter = self::createClient();
        $result = new RawHttpResult($this->createResponse([
            'choices' => [
                [
                    'finish_reason' => 'tool_calls',
                    'message' => [
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"city":"Paris"}',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]));

        $converted = $converter->convert($result);

        $this->assertInstanceOf(ToolCallResult::class, $converted);
    }

    public function testConvertToolCallsFinishReasonWithContentFallback()
    {
        $converter = self::createClient();
        $result = new RawHttpResult($this->createResponse([
            'choices' => [
                [
                    'finish_reason' => 'tool_calls',
                    'message' => [
                        'content' => '{"recipe":"Pasta Carbonara","ingredients":["pasta","eggs","bacon"]}',
                    ],
                ],
            ],
        ]));

        $converted = $converter->convert($result);

        $this->assertInstanceOf(TextResult::class, $converted);
        $this->assertSame(
            '{"recipe":"Pasta Carbonara","ingredients":["pasta","eggs","bacon"]}',
            $converted->getContent(),
        );
    }

    public function testConvertToolCallsFinishReasonWithoutContentThrows()
    {
        $converter = self::createClient();
        $result = new RawHttpResult($this->createResponse([
            'choices' => [
                [
                    'finish_reason' => 'tool_calls',
                    'message' => [],
                ],
            ],
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported finish reason "tool_calls"');
        $converter->convert($result);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createResponse(array $data): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($data),
        ]);

        return $httpClient->request('POST', 'https://litellm.example.com/v1/chat/completions');
    }

    private static function createClient(): ChatCompletionsClient
    {
        return new ChatCompletionsClient(new HttpTransport(new MockHttpClient(), 'https://litellm.example.com'));
    }
}
