<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Higgsfield\Contract\ImageNormalizer;
use Symfony\AI\Platform\Bridge\Higgsfield\GenerationClient;
use Symfony\AI\Platform\Bridge\Higgsfield\Higgsfield;
use Symfony\AI\Platform\Bridge\Higgsfield\HiggsfieldJobClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GenerationClientTest extends TestCase
{
    private const BASE_URL = 'https://platform.higgsfield.ai';

    public function testSupportsModel()
    {
        $client = new GenerationClient(new MockHttpClient([], self::BASE_URL));

        $this->assertTrue($client->supports(new Higgsfield('higgsfield-ai/soul/v2/standard')));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testClientSubmitsTheGenerationWithoutWaitingForIt()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"request_id": "req-123", "status": "queued"}'),
        ], self::BASE_URL);

        $client = new GenerationClient($httpClient);

        $result = $client->request(new Higgsfield('higgsfield-ai/soul/v2/standard', [Capability::TEXT_TO_IMAGE]), 'A cat on a kitchen table');

        $this->assertInstanceOf(RawHttpResult::class, $result);
        $this->assertSame('req-123', $result->getData()['request_id']);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientSendsPromptAndOptionsToTheModelEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://platform.higgsfield.ai/higgsfield-ai/soul/v2/standard', $url);
            $this->assertSame(['prompt' => 'A cat', 'aspect_ratio' => '9:16'], json_decode($options['body'], true));

            return new MockResponse('{"request_id": "req-123", "status": "queued"}');
        }, self::BASE_URL);

        $client = new GenerationClient($httpClient);

        $client->request(new Higgsfield('higgsfield-ai/soul/v2/standard', [Capability::TEXT_TO_IMAGE]), 'A cat', ['aspect_ratio' => '9:16']);
    }

    public function testClientUploadsNormalizedImageAndPassesItsPublicUrl()
    {
        $image = Image::fromFile(\dirname(__DIR__, 6).'/fixtures/image.jpg');
        $payload = (new ImageNormalizer())->normalize($image);

        $httpClient = new MockHttpClient([
            function (string $method, string $url, array $options): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('https://platform.higgsfield.ai/files/generate-upload-url', $url);
                $this->assertSame(['content_type' => 'image/jpeg'], json_decode($options['body'], true));

                return new JsonMockResponse([
                    'public_url' => 'https://cdn.example.com/image.jpeg',
                    'upload_url' => 'https://bucket.example.com/image.jpeg?signature=abc',
                    'upload_headers' => ['Content-Type' => 'image/jpeg', 'x-amz-tagging' => 'retention=temporary'],
                ]);
            },
            function (string $method, string $url, array $options) use ($image): MockResponse {
                $this->assertSame('PUT', $method);
                $this->assertSame('https://bucket.example.com/image.jpeg?signature=abc', $url);
                $this->assertContains('x-amz-tagging: retention=temporary', $options['headers']);
                $this->assertSame($image->asBinary(), $options['body']);

                return new MockResponse();
            },
            function (string $method, string $url, array $options): MockResponse {
                $this->assertSame('https://platform.higgsfield.ai/kling-video/v2.5-turbo/pro/image-to-video', $url);
                $this->assertSame(['image_url' => 'https://cdn.example.com/image.jpeg', 'prompt' => 'Slowly zoom in'], json_decode($options['body'], true));

                return new MockResponse('{"request_id": "req-123", "status": "queued"}');
            },
        ], self::BASE_URL);

        $client = new GenerationClient($httpClient);

        $result = $client->request(new Higgsfield('kling-video/v2.5-turbo/pro/image-to-video', [Capability::IMAGE_TO_VIDEO]), $payload, ['prompt' => 'Slowly zoom in']);

        $this->assertSame('req-123', $result->getData()['request_id']);
        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testConvertReturnsAJobHandleForTheSubmittedGeneration()
    {
        $result = self::createClient()->convert(self::rawResult('{"request_id": "req-123", "status": "queued"}'));

        $this->assertInstanceOf(JobResult::class, $result);
        $this->assertSame('req-123', $result->getContent()->getId());
        $this->assertSame('higgsfield', $result->getContent()->getProvider());
        $this->assertSame(HiggsfieldJobClient::DEFAULT_MAX_DURATION, $result->getContent()->getMaxDuration());
        $this->assertSame(HiggsfieldJobClient::DEFAULT_POLL_INTERVAL, $result->getContent()->getPollInterval());
        $this->assertSame('requests/req-123/status', $result->getContent()->get('status_path'));
    }

    public function testConvertNamesTheProviderTheJobClientServes()
    {
        $result = self::createClient('my-higgsfield')->convert(self::rawResult('{"request_id": "req-123", "status": "queued"}'));

        $this->assertInstanceOf(JobResult::class, $result);
        $this->assertSame('my-higgsfield', $result->getContent()->getProvider());
    }

    public function testConvertThrowsWhenRequestIdMissing()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Higgsfield API error: "invalid credentials".');

        self::createClient()->convert(self::rawResult('{"detail": "invalid credentials"}'));
    }

    public function testConvertThrowsOnHttpError()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('invalid credentials');

        self::createClient()->convert(self::rawResult('{"detail": "invalid credentials"}', 401));
    }

    private static function createClient(string $name = 'higgsfield'): GenerationClient
    {
        return new GenerationClient(new MockHttpClient([], self::BASE_URL), $name);
    }

    private static function rawResult(string $body, int $statusCode = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new MockResponse($body, ['http_code' => $statusCode]));

        return new RawHttpResult($httpClient->request('POST', 'https://platform.higgsfield.ai/higgsfield-ai/soul/v2/standard'));
    }
}
