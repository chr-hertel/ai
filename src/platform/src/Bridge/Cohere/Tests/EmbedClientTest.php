<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\Cohere;
use Symfony\AI\Platform\Bridge\Cohere\EmbedClient;
use Symfony\AI\Platform\Bridge\Cohere\Embeddings;
use Symfony\AI\Platform\Bridge\Cohere\InputType;
use Symfony\AI\Platform\Bridge\Cohere\MetaBilledUnitsTokenUsageExtractor;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class EmbedClientTest extends TestCase
{
    public function testItSupportsEmbeddingsModel()
    {
        $client = new EmbedClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Embeddings('embed-english-v3.0')));
    }

    public function testItDoesNotSupportCohereModel()
    {
        $client = new EmbedClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Cohere('command-a-03-2025')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.cohere.com/v2/embed', $url);
            $this->assertStringContainsString('Bearer test-key', $options['normalized_headers']['authorization'][0]);

            $body = json_decode($options['body'], true);
            $this->assertSame('embed-english-v3.0', $body['model']);
            $this->assertSame(['Hello, world!'], $body['texts']);
            $this->assertSame('search_document', $body['input_type']);

            return new MockResponse();
        }]);

        $client = new EmbedClient($httpClient, 'test-key');

        $client->request(new Embeddings('embed-english-v3.0'), 'Hello, world!');
    }

    public function testItUsesInputTypeFromOptions()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame('search_query', $body['input_type']);

            return new MockResponse();
        }]);

        $client = new EmbedClient($httpClient, 'test-key');

        $client->request(new Embeddings('embed-english-v3.0'), 'Hello, world!', [
            'input_type' => InputType::SearchQuery,
        ]);
    }

    public function testItUsesInputTypeFromModelOptions()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame('classification', $body['input_type']);

            return new MockResponse();
        }]);

        $client = new EmbedClient($httpClient, 'test-key');

        $model = new Embeddings('embed-english-v3.0', [], ['input_type' => InputType::Classification]);
        $client->request($model, 'Hello, world!');
    }

    public function testItThrowsExceptionOnNon200StatusCode()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        self::request(new MockResponse('Internal Server Error', ['http_code' => 500]));
    }

    public function testItThrowsModelNotFoundExceptionOnNotFound()
    {
        $this->expectException(ModelNotFoundException::class);

        self::request(new JsonMockResponse(['message' => "model 'embed-z' not found"], ['http_code' => 404]));
    }

    public function testThrowsRateLimitExceededExceptionWithRetryAfterHeader()
    {
        try {
            self::request(new JsonMockResponse(['message' => 'trial key rate limit exceeded'], ['http_code' => 429, 'response_headers' => ['retry-after' => '30']]));
            $this->fail('Expected a RateLimitExceededException to be thrown.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame(30, $e->getRetryAfter());
        }
    }

    public function testItConvertsAResponseToAVectorResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'embeddings' => [
                'float' => [
                    [0.1, 0.2, 0.3],
                ],
            ],
        ]);

        $converter = new EmbedClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertSame([0.1, 0.2, 0.3], $result->getContent()[0]->getData());
    }

    public function testItConvertsMultipleEmbeddings()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'embeddings' => [
                'float' => [
                    [0.1, 0.2, 0.3],
                    [0.4, 0.5, 0.6],
                ],
            ],
        ]);

        $converter = new EmbedClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertCount(2, $result->getContent());
        $this->assertSame([0.1, 0.2, 0.3], $result->getContent()[0]->getData());
        $this->assertSame([0.4, 0.5, 0.6], $result->getContent()[1]->getData());
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainData()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['invalid' => 'response']);

        $converter = new EmbedClient(new MockHttpClient(), 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain embedding data.');

        $converter->convert(new RawHttpResult($response));
    }

    public function testGetTokenUsageExtractor()
    {
        $converter = new EmbedClient(new MockHttpClient(), 'test-key');

        $this->assertInstanceOf(MetaBilledUnitsTokenUsageExtractor::class, $converter->getTokenUsageExtractor());
    }

    private static function request(MockResponse $response): void
    {
        $client = new EmbedClient(new MockHttpClient($response), 'test-key');
        $client->convert($client->request(new Embeddings('embed-english-v3.0'), 'Hello, world!'));
    }
}
