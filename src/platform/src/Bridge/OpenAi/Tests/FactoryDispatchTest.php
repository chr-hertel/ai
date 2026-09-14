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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FactoryDispatchTest extends TestCase
{
    public function testGptDefaultsToResponsesEndpoint()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'output' => [
                    [
                        'type' => 'message',
                        'role' => 'assistant',
                        'id' => 'msg_1',
                        'content' => [['type' => 'output_text', 'text' => 'responses says hi']],
                    ],
                ],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 4],
            ]);
        });

        $platform = Factory::createPlatform('sk-test', $httpClient);
        $result = $platform->invoke('gpt-4o', new MessageBag(Message::ofUser('hi')));

        $this->assertSame('https://api.openai.com/v1/responses', $capturedUrl);
        $this->assertSame('gpt-4o', $capturedBody['model']);
        $this->assertArrayHasKey('input', $capturedBody, 'Responses API expects "input", not "messages"');

        $textResult = $result->getResult();
        $this->assertInstanceOf(TextResult::class, $textResult);
        $this->assertSame('responses says hi', $textResult->getContent());
    }

    public function testGptCanBeInvokedOverChatCompletionsEndpoint()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'chat says hi'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 4],
            ]);
        });

        $platform = Factory::createPlatform('sk-test', $httpClient, useChatCompletions: true);
        $result = $platform->invoke('gpt-4o', new MessageBag(Message::ofUser('hi')));

        $this->assertSame('https://api.openai.com/v1/chat/completions', $capturedUrl);
        $this->assertSame('gpt-4o', $capturedBody['model']);
        $this->assertArrayHasKey('messages', $capturedBody, 'Chat Completions API expects "messages", not "input"');
        $this->assertArrayNotHasKey('input', $capturedBody);

        $textResult = $result->getResult();
        $this->assertInstanceOf(TextResult::class, $textResult);
        $this->assertSame('chat says hi', $textResult->getContent());
    }

    public function testEachContractIsServedByAPlatformBuiltForIt()
    {
        $capturedUrls = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrls) {
            $capturedUrls[] = $url;

            if (str_contains($url, '/v1/responses')) {
                return new JsonMockResponse([
                    'output' => [['type' => 'message', 'role' => 'assistant', 'id' => 'm', 'content' => [['type' => 'output_text', 'text' => 'A']]]],
                ]);
            }

            return new JsonMockResponse([
                'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'B'], 'finish_reason' => 'stop']],
            ]);
        });

        $responses = Factory::createPlatform('sk-test', $httpClient);
        $chat = Factory::createPlatform('sk-test', $httpClient, useChatCompletions: true);

        $a = $responses->invoke('gpt-4o', new MessageBag(Message::ofUser('hi')));
        $b = $chat->invoke('gpt-4o', new MessageBag(Message::ofUser('hi')));

        $this->assertSame('https://api.openai.com/v1/responses', $capturedUrls[0]);
        $this->assertSame('https://api.openai.com/v1/chat/completions', $capturedUrls[1]);

        $this->assertSame('A', $a->getResult()->getContent());
        $this->assertSame('B', $b->getResult()->getContent());
    }

    public function testEmbeddingsEndpointRoutesThroughDispatcher()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3]],
                    ['embedding' => [0.4, 0.5, 0.6]],
                ],
                'usage' => ['prompt_tokens' => 2, 'total_tokens' => 2],
            ]);
        });

        $platform = Factory::createPlatform('sk-test', $httpClient);
        $result = $platform->invoke('text-embedding-3-small', ['hello', 'world']);

        $this->assertSame('https://api.openai.com/v1/embeddings', $capturedUrl);
        $this->assertSame('text-embedding-3-small', $capturedBody['model']);
        $this->assertSame(['hello', 'world'], $capturedBody['input']);

        $vectorResult = $result->getResult();
        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertCount(2, $vectorResult->getContent());
    }
}
