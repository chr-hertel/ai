<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\UniversalAi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\ImageGeneration;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\ImageGenerationClient;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ImageGenerationClientTest extends TestCase
{
    public function testItSupportsImageGenerationModelOnly()
    {
        $client = new ImageGenerationClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $this->assertTrue($client->supports(new ImageGeneration('image/generation/openai')));
        $this->assertFalse($client->supports(new Ocr('ocr/ocr/google')));
    }

    public function testItConvertsGeneratedImageToBinaryResult()
    {
        $client = new ImageGenerationClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $result = $client->convert($this->rawResult([
            'status' => 'success',
            'provider' => 'openai',
            'cost' => '0.042000000',
            'output' => [
                'items' => [
                    [
                        'image' => base64_encode('png-binary-content'),
                        'image_resource_url' => 'https://example.com/generated.png',
                    ],
                ],
            ],
        ]));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('png-binary-content', $result->getContent());
        $this->assertSame('image/png', $result->getMimeType());
    }

    /**
     * "num_images" accepts up to 10 and every generated image is billed, so none may be
     * dropped.
     */
    public function testItConvertsEveryGeneratedImageToAMultiPartResult()
    {
        $client = new ImageGenerationClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $result = $client->convert($this->rawResult([
            'status' => 'success',
            'output' => [
                'items' => [
                    ['image' => base64_encode('first'), 'image_resource_url' => 'https://example.com/1.png'],
                    ['image' => base64_encode('second'), 'image_resource_url' => 'https://example.com/2.png'],
                    ['image' => base64_encode('third'), 'image_resource_url' => 'https://example.com/3.png'],
                ],
            ],
        ]));

        $this->assertInstanceOf(MultiPartResult::class, $result);

        $images = $result->getContent();
        $this->assertCount(3, $images);
        $this->assertSame('first', $images[0]->getContent());
        $this->assertSame('second', $images[1]->getContent());
        $this->assertSame('third', $images[2]->getContent());
        $this->assertSame([
            'https://example.com/1.png',
            'https://example.com/2.png',
            'https://example.com/3.png',
        ], $result->getMetadata()->get('image_resource_urls'));
    }

    public function testItExposesTheGatewayCostAndProviderAsMetadata()
    {
        $client = new ImageGenerationClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $result = $client->convert($this->rawResult([
            'status' => 'success',
            'provider' => 'stabilityai',
            'cost' => '0.015000000',
            'output' => ['items' => [['image' => base64_encode('png'), 'image_resource_url' => 'https://example.com/a.png']]],
        ]));

        $this->assertSame('stabilityai', $result->getMetadata()->get('provider'));
        $this->assertSame(0.015, $result->getMetadata()->get('cost'));
        $this->assertSame(['https://example.com/a.png'], $result->getMetadata()->get('image_resource_urls'));
    }

    public function testItThrowsWhenProviderFails()
    {
        $client = new ImageGenerationClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI request failed: "Prompt rejected."');

        $client->convert($this->rawResult([
            'status' => 'fail',
            'output' => null,
            'error' => ['message' => 'Prompt rejected.'],
        ]));
    }

    public function testItThrowsWhenNoImageIsGenerated()
    {
        $client = new ImageGenerationClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain a generated image.');

        $client->convert($this->rawResult(['status' => 'success', 'output' => ['items' => []]]));
    }

    public function testItThrowsWhenOutputHasNoItemsKey()
    {
        $client = new ImageGenerationClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain a generated image.');

        $client->convert($this->rawResult(['status' => 'success', 'output' => []]));
    }

    public function testItThrowsWhenEveryItemLacksAnImage()
    {
        $client = new ImageGenerationClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain a generated image.');

        $client->convert($this->rawResult([
            'status' => 'success',
            'output' => ['items' => [['image_resource_url' => 'https://example.com/a.png'], ['image' => '']]],
        ]));
    }

    public function testItThrowsWhenImageIsNotValidBase64()
    {
        $client = new ImageGenerationClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The provided data is not valid base64-encoded data.');

        $client->convert($this->rawResult([
            'status' => 'success',
            'output' => ['items' => [['image' => '!!! not base64 !!!']]],
        ]));
    }

    public function testItOmitsMetadataWhenTheGatewayReportsNeitherCostNorProvider()
    {
        $client = new ImageGenerationClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $result = $client->convert($this->rawResult([
            'status' => 'success',
            'output' => ['items' => [['image' => base64_encode('png')]]],
        ]));

        $this->assertNull($result->getMetadata()->get('provider'));
        $this->assertNull($result->getMetadata()->get('cost'));
        $this->assertNull($result->getMetadata()->get('image_resource_urls'));
    }

    /**
     * The payload is base64 whatever the format is, and the response names that format nowhere
     * but in the extension of the resource URL.
     */
    #[DataProvider('provideResourceUrls')]
    public function testItReadsTheImageFormatFromTheResourceUrl(?string $resourceUrl, string $expectedMimeType)
    {
        $client = new ImageGenerationClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');
        $item = ['image' => base64_encode('binary-content')];

        if (null !== $resourceUrl) {
            $item['image_resource_url'] = $resourceUrl;
        }

        $result = $client->convert($this->rawResult([
            'status' => 'success',
            'output' => ['items' => [$item]],
        ]));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame($expectedMimeType, $result->getMimeType());
    }

    /**
     * @return iterable<string, array{0: string|null, 1: string}>
     */
    public static function provideResourceUrls(): iterable
    {
        yield 'png' => ['https://cdn.example.com/abc_.png?Expires=1&Signature=x', 'image/png'];
        yield 'jpeg' => ['https://cdn.example.com/abc_.jpeg?Expires=1', 'image/jpeg'];
        yield 'jpg' => ['https://cdn.example.com/abc_.jpg', 'image/jpeg'];
        yield 'webp' => ['https://cdn.example.com/abc_.webp', 'image/webp'];
        yield 'unknown extension falls back to png' => ['https://cdn.example.com/abc_.bin', 'image/png'];
        yield 'no resource url falls back to png' => [null, 'image/png'];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function rawResult(array $data): RawHttpResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data));

        return new RawHttpResult($httpClient->request('POST', 'https://api.edenai.run/v3/universal-ai'));
    }
}
