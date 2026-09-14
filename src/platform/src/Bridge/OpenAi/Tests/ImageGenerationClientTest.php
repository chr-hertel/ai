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
use Symfony\AI\Platform\Bridge\OpenAi\Image;
use Symfony\AI\Platform\Bridge\OpenAi\ImageGenerationClient;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Image as ImageContent;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

final class ImageGenerationClientTest extends TestCase
{
    private const EMPTY_PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = self::client();

        $this->assertTrue($modelClient->supports(new Image('gpt-image-1')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/images/generations', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"n":1,"model":"gpt-image-1","prompt":"foo"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new Image('gpt-image-1'), 'foo', ['n' => 1]);
    }

    public function testItEditsAnImageUsingTheEditsEndpoint()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/images/edits', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            $contentType = $options['normalized_headers']['content-type'][0];
            self::assertStringStartsWith('Content-Type: multipart/form-data; boundary=', $contentType);

            // Depending on the HttpClient version the normalized body is a string, a generator, or a
            // Closure(int): string streaming chunks; materialize all three into a single string.
            $rawBody = $options['body'];
            if (\is_string($rawBody)) {
                $body = $rawBody;
            } elseif ($rawBody instanceof \Closure) {
                $body = '';
                while ('' !== ($chunk = $rawBody(8192))) {
                    $body .= $chunk;
                }
            } else {
                $body = '';
                foreach ($rawBody as $chunk) {
                    $body .= $chunk;
                }
            }
            self::assertStringContainsString('name="model"', $body);
            self::assertStringContainsString('gpt-image-1', $body);
            self::assertStringContainsString('name="prompt"', $body);
            self::assertStringContainsString('make it red', $body);
            self::assertStringContainsString('name="image"; filename="image.jpg"', $body);
            self::assertStringContainsString('Content-Type: image/jpeg', $body);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new Image('gpt-image-1'), 'make it red', [
            'image' => ImageContent::fromFile(\dirname(__DIR__, 6).'/fixtures/image.jpg'),
        ]);
    }

    public function testItThrowsWhenPromptIsNotAString()
    {
        $modelClient = self::client();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The image prompt must be a string');

        $modelClient->request(new Image('gpt-image-1'), ['not', 'a', 'string']);
    }

    public function testItConvertsASingleImageToABinaryResult()
    {
        $result = self::client()->convert(new InMemoryRawResult([
            'data' => [
                ['b64_json' => self::EMPTY_PIXEL],
            ],
        ]));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('image/png', $result->getMimeType());
        $this->assertSame(self::EMPTY_PIXEL, $result->toBase64());
    }

    public function testItConvertsMultipleImagesToAMultiPartResult()
    {
        $result = self::client()->convert(new InMemoryRawResult([
            'data' => [
                ['b64_json' => self::EMPTY_PIXEL],
                ['b64_json' => self::EMPTY_PIXEL],
            ],
        ]));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertCount(2, $result->getContent());
        $this->assertContainsOnlyInstancesOf(BinaryResult::class, $result->getContent());
    }

    public function testItUsesTheRequestedOutputFormatAsMimeType()
    {
        $result = self::client()->convert(new InMemoryRawResult([
            'data' => [
                ['b64_json' => self::EMPTY_PIXEL],
            ],
        ]), ['output_format' => 'webp']);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('image/webp', $result->getMimeType());
    }

    public function testItThrowsExceptionWhenNoImageWasGenerated()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No image generated.');

        self::client()->convert(new InMemoryRawResult(['data' => []]));
    }

    public function testItThrowsModelNotFoundExceptionForAnUnknownModel()
    {
        $client = self::client(new MockHttpClient(new JsonMockResponse(
            ['error' => ['message' => 'The model "gpt-image-0" does not exist.']],
            ['http_code' => 404],
        )));

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('The model "gpt-image-0" does not exist.');

        $client->convert($client->request(new Image('gpt-image-0'), 'a red apple'));
    }

    private static function client(MockHttpClient $httpClient = new MockHttpClient()): ImageGenerationClient
    {
        return new ImageGenerationClient(new HttpTransport($httpClient, 'sk-api-key'));
    }
}
