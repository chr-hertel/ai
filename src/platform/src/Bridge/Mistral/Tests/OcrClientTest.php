<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Embeddings;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Bridge\Mistral\Ocr;
use Symfony\AI\Platform\Bridge\Mistral\Ocr\Result\OcrResult;
use Symfony\AI\Platform\Bridge\Mistral\OcrClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OcrClientTest extends TestCase
{
    public function testItSupportsOcrModelOnly()
    {
        $client = new OcrClient(new MockHttpClient(), 'sk-test-key');

        $this->assertTrue($client->supports(new Ocr('mistral-ocr-latest')));
        $this->assertFalse($client->supports(new Mistral('mistral-large-latest')));
        $this->assertFalse($client->supports(new Embeddings('mistral-embed')));
    }

    public function testItSendsDocumentToOcrEndpoint()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.mistral.ai/v1/ocr', $url);

            $body = json_decode($options['body'], true);
            self::assertSame('mistral-ocr-latest', $body['model']);
            self::assertSame([
                'type' => 'document_url',
                'document_url' => 'https://example.com/document.pdf',
            ], $body['document']);

            return new JsonMockResponse(['pages' => []]);
        });

        $client = new OcrClient($httpClient, 'sk-test-key');
        $client->request(new Ocr('mistral-ocr-latest'), [
            'type' => 'document_url',
            'document_url' => 'https://example.com/document.pdf',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItForwardsOptions()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $body = json_decode($options['body'], true);
            self::assertTrue($body['include_image_base64']);

            return new JsonMockResponse(['pages' => []]);
        });

        $client = new OcrClient($httpClient, 'sk-test-key');
        $client->request(new Ocr('mistral-ocr-latest'), [
            'type' => 'image_url',
            'image_url' => 'https://example.com/image.png',
        ], ['include_image_base64' => true]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItThrowsExceptionForStringPayload()
    {
        $client = new OcrClient(new MockHttpClient(), 'sk-test-key');

        $this->expectException(InvalidArgumentException::class);

        $client->request(new Ocr('mistral-ocr-latest'), 'not-an-array');
    }

    public function testItConvertsPagesIntoStructuredResult()
    {
        $response = new JsonMockResponse([
            'model' => 'mistral-ocr-latest',
            'pages' => [
                [
                    'index' => 0,
                    'markdown' => "# Title\n\nFirst page.",
                    'dimensions' => ['width' => 2480, 'height' => 3508, 'dpi' => 200],
                    'images' => [
                        [
                            'id' => 'img-0',
                            'top_left_x' => 10,
                            'top_left_y' => 20,
                            'bottom_right_x' => 110,
                            'bottom_right_y' => 220,
                            'image_base64' => 'data:image/png;base64,abc',
                        ],
                    ],
                ],
                [
                    'index' => 1,
                    'markdown' => 'Second page.',
                ],
            ],
            'document_annotation' => '{"language":"en"}',
            'usage_info' => ['pages_processed' => 2, 'doc_size_bytes' => 12345],
        ]);

        $result = (new OcrClient(new MockHttpClient(), 'sk-test-key'))->convert(new RawHttpResult($this->responseOf($response)));

        $this->assertInstanceOf(ObjectResult::class, $result);

        $ocr = $result->getContent();
        \assert($ocr instanceof OcrResult);

        $this->assertSame('mistral-ocr-latest', $ocr->getModel());
        $this->assertCount(2, $ocr->getPages());
        $this->assertSame("# Title\n\nFirst page.\n\nSecond page.", $ocr->getMarkdown());
        $this->assertSame('{"language":"en"}', $ocr->getDocumentAnnotation());
        $this->assertSame(['pages_processed' => 2, 'doc_size_bytes' => 12345], $ocr->getUsageInfo());

        $firstPage = $ocr->getPages()[0];
        $this->assertSame(0, $firstPage->getIndex());
        $this->assertSame(['width' => 2480, 'height' => 3508, 'dpi' => 200], $firstPage->getDimensions());
        $this->assertCount(1, $firstPage->getImages());

        $image = $firstPage->getImages()[0];
        $this->assertSame('img-0', $image->getId());
        $this->assertSame(10, $image->getTopLeftX());
        $this->assertSame(220, $image->getBottomRightY());
        $this->assertSame('data:image/png;base64,abc', $image->getImageBase64());
    }

    public function testItThrowsWhenPagesAreMissing()
    {
        $result = new RawHttpResult($this->responseOf(new JsonMockResponse(['model' => 'mistral-ocr-latest'])));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain pages.');

        (new OcrClient(new MockHttpClient(), 'sk-test-key'))->convert($result);
    }

    private function responseOf(JsonMockResponse $response): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        $client = new MockHttpClient($response);

        return $client->request('POST', 'https://api.mistral.ai/v1/ocr');
    }
}
