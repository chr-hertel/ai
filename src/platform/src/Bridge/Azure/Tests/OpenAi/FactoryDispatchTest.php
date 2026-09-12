<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Tests\OpenAi;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Azure\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * The Azure provider reuses the OpenAI contract handlers, so each catalog model
 * has to reach the right one through the Azure transport.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FactoryDispatchTest extends TestCase
{
    public function testResponsesModelReachesTheResponsesContract()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody): JsonMockResponse {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => 'hello from azure']],
                ]],
            ]);
        });

        $platform = Factory::createPlatform('test.openai.azure.com', 'my-deployment', '2024-10-21', 'test-key', $httpClient);
        $result = $platform->invoke('gpt-4o', new MessageBag(Message::ofUser('hi')));

        $this->assertSame('https://test.openai.azure.com/openai/v1/responses', $capturedUrl);
        // The deployment, not the model name, addresses the Azure resource.
        $this->assertSame('my-deployment', $capturedBody['model']);

        $textResult = $result->getResult();
        $this->assertInstanceOf(TextResult::class, $textResult);
        $this->assertSame('hello from azure', $textResult->getContent());
    }

    public function testEmbeddingsModelReachesTheEmbeddingsContract()
    {
        $capturedUrl = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl): JsonMockResponse {
            $capturedUrl = $url;

            return new JsonMockResponse(['data' => [['embedding' => [0.1, 0.2, 0.3]]]]);
        });

        $platform = Factory::createPlatform('test.openai.azure.com', 'embed-deployment', '2024-10-21', 'test-key', $httpClient);
        $result = $platform->invoke('text-embedding-3-large', 'Hello, world!');

        $this->assertSame(
            'https://test.openai.azure.com/openai/deployments/embed-deployment/embeddings?api-version=2024-10-21',
            $capturedUrl,
        );

        $vectorResult = $result->getResult();
        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertSame([0.1, 0.2, 0.3], $vectorResult->getContent()[0]->getData());
    }

    public function testWhisperModelReachesTheTranscriptionContract()
    {
        $capturedUrl = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl): JsonMockResponse {
            $capturedUrl = $url;

            return new JsonMockResponse(['text' => 'transcribed']);
        });

        $platform = Factory::createPlatform('test.openai.azure.com', 'whisper-deployment', '2024-10-21', 'test-key', $httpClient);
        $result = $platform->invoke('whisper-1', ['file' => 'audio-data']);

        $this->assertSame(
            'https://test.openai.azure.com/openai/deployments/whisper-deployment/audio/transcriptions?api-version=2024-10-21',
            $capturedUrl,
        );

        $textResult = $result->getResult();
        $this->assertInstanceOf(TextResult::class, $textResult);
        $this->assertSame('transcribed', $textResult->getContent());
    }
}
