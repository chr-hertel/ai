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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\EmbeddingsClient;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class EmbeddingsClientTest extends TestCase
{
    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = self::client();

        $this->assertTrue($modelClient->supports(new Embeddings('text-embedding-3-small')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"text-embedding-3-small","input":"test text"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new Embeddings('text-embedding-3-small'), 'test text', []);
    }

    public function testItIsExecutingTheCorrectRequestWithCustomOptions()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"dimensions":256,"model":"text-embedding-3-large","input":"test text"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new Embeddings('text-embedding-3-large'), 'test text', ['dimensions' => 256]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayInput()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"text-embedding-3-small","input":["text1","text2","text3"]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new Embeddings('text-embedding-3-small'), ['text1', 'text2', 'text3'], []);
    }

    #[TestWith(['EU', 'https://eu.api.openai.com/v1/embeddings'])]
    #[TestWith(['US', 'https://us.api.openai.com/v1/embeddings'])]
    #[TestWith([null, 'https://api.openai.com/v1/embeddings'])]
    public function testItUsesCorrectBaseUrl(?string $region, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new EmbeddingsClient(new HttpTransport($httpClient, 'sk-api-key', $region));
        $modelClient->request(new Embeddings('text-embedding-3-small'), 'test input', []);
    }

    public function testItConvertsAResponseToAVectorResult()
    {
        $vectorResult = self::client()->convert(new InMemoryRawResult([
            'object' => 'list',
            'data' => [
                ['object' => 'embedding', 'index' => 0, 'embedding' => [0.3, 0.4, 0.4]],
                ['object' => 'embedding', 'index' => 1, 'embedding' => [0.0, 0.0, 0.2]],
            ],
        ]));
        $convertedContent = $vectorResult->getContent();

        $this->assertCount(2, $convertedContent);

        $this->assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
    }

    public function testItThrowsExceptionWhenTheResponseDoesNotContainData()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain data.');

        self::client()->convert(new InMemoryRawResult([]));
    }

    public function testItReportsTheStatusCodeAndBodyOfAnUnexpectedHttpResponse()
    {
        $client = self::client(new MockHttpClient(new JsonMockResponse(
            ['error' => ['message' => 'Forbidden']],
            ['http_code' => 403],
        )));

        $raw = $client->request(new Embeddings('text-embedding-3-small'), 'test text');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response from OpenAI API does not contain "data" key. StatusCode: "403". Response: "{"error":{"message":"Forbidden"}}".');

        $client->convert($raw);
    }

    private static function client(MockHttpClient $httpClient = new MockHttpClient()): EmbeddingsClient
    {
        return new EmbeddingsClient(new HttpTransport($httpClient, 'sk-api-key'));
    }
}
