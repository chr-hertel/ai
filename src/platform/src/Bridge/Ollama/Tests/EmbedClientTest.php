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
use Symfony\AI\Platform\Bridge\Ollama\EmbedClient;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class EmbedClientTest extends TestCase
{
    public function testEmbedRequestMovesNonTopLevelOptionsIntoNestedOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('POST', $method);
            $this->assertSame('http://127.0.0.1:1234/api/embed', $url);

            $json = json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR);

            $this->assertSame('embeddinggemma', $json['model']);
            $this->assertSame('hello', $json['input']);

            $this->assertFalse($json['truncate']);
            $this->assertSame(512, $json['dimensions']);

            $this->assertArrayHasKey('options', $json);
            $this->assertSame(0.1, $json['options']['temperature']);

            $this->assertArrayNotHasKey('temperature', $json);

            return new JsonMockResponse([
                'model' => 'embeddinggemma',
                'embeddings' => [[0.1, 0.2]],
            ]);
        }, 'http://127.0.0.1:1234');

        $client = new EmbedClient($httpClient);

        $client->request(
            new Ollama('embeddinggemma', [Capability::EMBEDDINGS]),
            'hello',
            [
                'truncate' => false,
                'dimensions' => 512,
                'temperature' => 0.1,
            ]
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItConvertsAResponseToAVectorResult()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn([
                'model' => 'all-minilm',
                'embeddings' => [
                    [0.3, 0.4, 0.4],
                    [0.0, 0.0, 0.2],
                ],
                'total_duration' => 14143917,
                'load_duration' => 1019500,
                'prompt_eval_count' => 8,
            ]);

        $vectorResult = (new EmbedClient(new MockHttpClient()))->convert(new RawHttpResult($result));
        $convertedContent = $vectorResult->getContent();

        $this->assertCount(2, $convertedContent);

        $this->assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
    }

    public function testThrowsExceptionWhenNoEmbeddings()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain embeddings.');

        (new EmbedClient(new MockHttpClient()))->convert(new InMemoryRawResult(['embeddings' => []]));
    }
}
