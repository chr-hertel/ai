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
use Symfony\AI\Platform\Bridge\Decart\Decart;
use Symfony\AI\Platform\Bridge\Decart\GenerateClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GenerateClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new GenerateClient(
            new MockHttpClient(),
            'my-api-key',
        );

        $this->assertTrue($client->supports(new Decart('lucy-pro-t2i', [Capability::TEXT_TO_IMAGE])));
        $this->assertTrue($client->supports(new Decart('lucy-pro-t2v', [Capability::TEXT_TO_VIDEO])));
        $this->assertFalse($client->supports(new Decart('lucy-pro-i2i', [Capability::IMAGE_TO_IMAGE])));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testClientCanGenerateTextToImage()
    {
        $imageContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/image.jpg');

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($imageContent): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.decart.ai/v1/generate/lucy-pro-t2i', $url);
            $this->assertContains('x-api-key: my-api-key', $options['headers']);
            $this->assertMatchesRegularExpression('{^Content-Type: multipart/form-data; boundary=}', $options['normalized_headers']['content-type'][0]);

            $body = '';
            while ('' !== $chunk = $options['body'](8192)) {
                $body .= $chunk;
            }

            $this->assertStringContainsString("name=\"prompt\"\r\n\r\nfoo\r\n", $body);

            return new MockResponse($imageContent, ['response_headers' => ['content-type' => 'image/jpeg']]);
        });

        $client = new GenerateClient(
            $httpClient,
            'my-api-key',
        );

        $client->request(new Decart('lucy-pro-t2i', [Capability::TEXT_TO_IMAGE]), [
            'text' => 'foo',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanGenerateTextToVideo()
    {
        $videoContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/ocean.mp4');

        $httpClient = new MockHttpClient([
            new MockResponse($videoContent, ['response_headers' => ['content-type' => 'video/mp4']]),
        ]);

        $client = new GenerateClient(
            $httpClient,
            'my-api-key',
        );

        $client->request(new Decart('lucy-pro-t2v', [Capability::TEXT_TO_VIDEO]), [
            'text' => 'foo',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('https://x.example.com/v1/generate/lucy-pro-t2i', $url);

            return new MockResponse('', ['response_headers' => ['content-type' => 'image/jpeg']]);
        });

        $client = new GenerateClient(
            $httpClient,
            'my-api-key',
            'https://x.example.com/v1/',
        );

        $client->request(new Decart('lucy-pro-t2i', [Capability::TEXT_TO_IMAGE]), 'foo');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testConvertReturnsBinaryResultWithResponseContentType()
    {
        $videoContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/ocean.mp4');

        $httpClient = new MockHttpClient([
            new MockResponse($videoContent, ['response_headers' => ['content-type' => 'video/mp4']]),
        ]);

        $client = new GenerateClient($httpClient, 'my-api-key');

        $result = $client->convert(new RawHttpResult($httpClient->request('POST', 'https://api.decart.ai/v1/generate/lucy-pro-t2v')));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('video/mp4', $result->getMimeType());
        $this->assertSame($videoContent, $result->getContent());
    }
}
