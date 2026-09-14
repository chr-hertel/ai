<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Voyage\EmbeddingsClient;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class EmbeddingsClientTest extends TestCase
{
    public function testItSendsExpectedRequest()
    {
        $model = new Voyage('some-text-embedding-model', []);
        $input = 'Hello, world!';

        $resultCallback = static function (
            string $method,
            string $url,
            array $options,
        ) use ($model, $input): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.voyageai.com/v1/embeddings', $url);
            self::assertSame(json_encode([
                'model' => $model->getName(),
                'input' => $input,
                'input_type' => null,
                'truncation' => true,
                'output_dimension' => 300,
                'encoding_format' => null,
            ]), $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new EmbeddingsClient($httpClient, '');

        $client->request($model, $input, [
            'dimensions' => 300,
        ]);
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url): MockResponse {
            $this->assertSame('https://x.example.com/v1/embeddings', $url);

            return new MockResponse();
        }]);

        $client = new EmbeddingsClient($httpClient, 'test-api-key', 'https://x.example.com/');
        $client->request(new Voyage('some-text-embedding-model', []), 'Hello, world!');
    }

    public function testItConvertsAResponseToAVectorResult()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn([
                'data' => [
                    [
                        'embedding' => [0.1, 0.2, 0.3],
                    ],
                ],
            ]);

        $converter = new EmbeddingsClient(new MockHttpClient(), '');
        $vectorResult = $converter->convert(new RawHttpResult($result));

        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertSame([0.1, 0.2, 0.3], $vectorResult->getContent()[0]->getData());
    }

    public function testItConvertsMultipleEmbeddings()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn([
                'data' => [
                    [
                        'embedding' => [0.1, 0.2, 0.3],
                    ],
                    [
                        'embedding' => [0.4, 0.5, 0.6],
                    ],
                ],
            ]);

        $converter = new EmbeddingsClient(new MockHttpClient(), '');
        $vectorResult = $converter->convert(new RawHttpResult($result));

        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertCount(2, $vectorResult->getContent());
        $this->assertSame([0.1, 0.2, 0.3], $vectorResult->getContent()[0]->getData());
        $this->assertSame([0.4, 0.5, 0.6], $vectorResult->getContent()[1]->getData());
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainData()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn(['invalid' => 'response']);

        $converter = new EmbeddingsClient(new MockHttpClient(), '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain embedding data.');

        $converter->convert(new RawHttpResult($result));
    }

    public function testItSupportsVoyageModel()
    {
        $converter = new EmbeddingsClient(new MockHttpClient(), '');

        $this->assertTrue($converter->supports(new Voyage('voyage-3-5')));
        $this->assertFalse($converter->supports(new Voyage('voyage-multimodal-3', [Capability::INPUT_MULTIMODAL])));
    }
}
