<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decart\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decart\Contract\ImageNormalizer;
use Symfony\AI\Platform\Bridge\Decart\Contract\VideoNormalizer;
use Symfony\AI\Platform\Bridge\Decart\Decart;
use Symfony\AI\Platform\Bridge\Decart\EditClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Video;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EditClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new EditClient(
            new MockHttpClient(),
            'my-api-key',
        );

        $this->assertTrue($client->supports(new Decart('lucy-pro-i2i', [Capability::IMAGE_TO_IMAGE])));
        $this->assertTrue($client->supports(new Decart('lucy-dev-i2v', [Capability::IMAGE_TO_VIDEO])));
        $this->assertTrue($client->supports(new Decart('lucy-pro-v2v', [Capability::VIDEO_TO_VIDEO])));
        $this->assertFalse($client->supports(new Decart('lucy-pro-t2i', [Capability::TEXT_TO_IMAGE])));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testClientCanGenerateImageToImage()
    {
        $normalizer = new ImageNormalizer();
        $imageContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/image.jpg');

        $payload = $normalizer->normalize(Image::fromFile(\dirname(__DIR__, 6).'/fixtures/image.jpg'));

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($imageContent): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.decart.ai/v1/generate/lucy-dev-i2v', $url);
            $this->assertContains('x-api-key: my-api-key', $options['headers']);

            $body = '';
            while ('' !== $chunk = $options['body'](8192)) {
                $body .= $chunk;
            }

            $this->assertStringContainsString("name=\"prompt\"\r\n\r\nfoo\r\n", $body);
            $this->assertStringContainsString('name="data"; filename="image.jpg"', $body);

            return new MockResponse($imageContent, ['response_headers' => ['content-type' => 'image/jpeg']]);
        });

        $client = new EditClient(
            $httpClient,
            'my-api-key',
        );

        $client->request(new Decart('lucy-dev-i2v', [Capability::IMAGE_TO_IMAGE]), $payload, [
            'prompt' => 'foo',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanGenerateImageToVideo()
    {
        $normalizer = new ImageNormalizer();
        $videoContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/ocean.mp4');

        $payload = $normalizer->normalize(Image::fromFile(\dirname(__DIR__, 6).'/fixtures/image.jpg'));

        $httpClient = new MockHttpClient([
            new MockResponse($videoContent, ['response_headers' => ['content-type' => 'video/mp4']]),
        ]);

        $client = new EditClient(
            $httpClient,
            'my-api-key',
        );

        $client->request(new Decart('lucy-dev-i2i', [Capability::IMAGE_TO_VIDEO]), $payload, [
            'prompt' => 'foo',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanGenerateVideoToVideo()
    {
        $normalizer = new VideoNormalizer();
        $videoContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/ocean.mp4');

        $payload = $normalizer->normalize(Video::fromFile(\dirname(__DIR__, 6).'/fixtures/ocean.mp4'));

        $httpClient = new MockHttpClient([
            new MockResponse($videoContent, ['response_headers' => ['content-type' => 'video/mp4']]),
        ]);

        $client = new EditClient(
            $httpClient,
            'my-api-key',
        );

        $client->request(new Decart('lucy-pro-v2v', [Capability::VIDEO_TO_VIDEO]), $payload, [
            'prompt' => 'foo',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testConvertReturnsBinaryResultWithResponseContentType()
    {
        $imageContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/image.jpg');

        $httpClient = new MockHttpClient([
            new MockResponse($imageContent, ['response_headers' => ['content-type' => 'image/jpeg']]),
        ]);

        $client = new EditClient($httpClient, 'my-api-key');

        $result = $client->convert(new RawHttpResult($httpClient->request('POST', 'https://api.decart.ai/v1/generate/lucy-pro-i2i')));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('image/jpeg', $result->getMimeType());
        $this->assertSame($imageContent, $result->getContent());
    }
}
