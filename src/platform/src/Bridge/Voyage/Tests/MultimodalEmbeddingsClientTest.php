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
use Symfony\AI\Platform\Bridge\Voyage\MultimodalEmbeddingsClient;
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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MultimodalEmbeddingsClientTest extends TestCase
{
    public function testItSendsExpectedRequest()
    {
        $model = new Voyage('some-multimodal-embedding-model', [Capability::INPUT_MULTIMODAL]);
        $input = 'Hello, world!';

        $resultCallback = static function (
            string $method,
            string $url,
            array $options,
        ) use ($model, $input): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.voyageai.com/v1/multimodalembeddings', $url);
            self::assertSame(json_encode([
                'model' => $model->getName(),
                'inputs' => $input,
                'input_type' => null,
                'truncation' => true,
                'output_encoding' => null,
            ]), $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new MultimodalEmbeddingsClient($httpClient, '');

        $client->request($model, $input, [
            'dimensions' => 300,
        ]);
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url): MockResponse {
            $this->assertSame('https://x.example.com/v1/multimodalembeddings', $url);

            return new MockResponse();
        }]);

        $client = new MultimodalEmbeddingsClient($httpClient, 'test-api-key', 'https://x.example.com/');
        $client->request(new Voyage('some-multimodal-embedding-model', [Capability::INPUT_MULTIMODAL]), 'Hello, world!');
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

        $converter = new MultimodalEmbeddingsClient(new MockHttpClient(), '');
        $vectorResult = $converter->convert(new RawHttpResult($result));

        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertSame([0.1, 0.2, 0.3], $vectorResult->getContent()[0]->getData());
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainData()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn(['invalid' => 'response']);

        $converter = new MultimodalEmbeddingsClient(new MockHttpClient(), '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain embedding data.');

        $converter->convert(new RawHttpResult($result));
    }

    public function testItSupportsMultimodalVoyageModel()
    {
        $converter = new MultimodalEmbeddingsClient(new MockHttpClient(), '');

        $this->assertTrue($converter->supports(new Voyage('voyage-multimodal-3', [Capability::INPUT_MULTIMODAL])));
        $this->assertFalse($converter->supports(new Voyage('voyage-3-5')));
    }
}
