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
use Symfony\AI\Platform\Bridge\Cohere\MetaBilledUnitsTokenUsageExtractor;
use Symfony\AI\Platform\Bridge\Cohere\RerankClient;
use Symfony\AI\Platform\Bridge\Cohere\Reranker;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class RerankClientTest extends TestCase
{
    public function testItSupportsRerankerModel()
    {
        $client = new RerankClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Reranker('rerank-v3.5')));
    }

    public function testItDoesNotSupportCohereModel()
    {
        $client = new RerankClient(new MockHttpClient(), 'test-key');

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
            $this->assertSame('https://api.cohere.com/v2/rerank', $url);
            $this->assertStringContainsString('Bearer test-key', $options['normalized_headers']['authorization'][0]);

            $body = json_decode($options['body'], true);
            $this->assertSame('rerank-v3.5', $body['model']);
            $this->assertSame('What is AI?', $body['query']);
            $this->assertSame(['Document about AI', 'Document about cooking'], $body['documents']);
            $this->assertArrayNotHasKey('top_n', $body);

            return new MockResponse();
        }]);

        $client = new RerankClient($httpClient, 'test-key');

        $client->request(new Reranker('rerank-v3.5'), [
            'query' => 'What is AI?',
            'texts' => ['Document about AI', 'Document about cooking'],
        ]);
    }

    public function testItSendsTopNOption()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame(3, $body['top_n']);

            return new MockResponse();
        }]);

        $client = new RerankClient($httpClient, 'test-key');

        $client->request(new Reranker('rerank-v3.5'), [
            'query' => 'What is AI?',
            'texts' => ['Doc 1', 'Doc 2', 'Doc 3', 'Doc 4'],
        ], ['top_n' => 3]);
    }

    public function testItThrowsExceptionForStringPayload()
    {
        $client = new RerankClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reranker payload must be an array with "query" and "texts" keys.');

        $client->request(new Reranker('rerank-v3.5'), 'invalid string payload');
    }

    public function testItThrowsExceptionForMissingQueryKey()
    {
        $client = new RerankClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reranker payload must be an array with "query" and "texts" keys.');

        $client->request(new Reranker('rerank-v3.5'), ['texts' => ['doc1']]);
    }

    public function testItThrowsExceptionForMissingTextsKey()
    {
        $client = new RerankClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reranker payload must be an array with "query" and "texts" keys.');

        $client->request(new Reranker('rerank-v3.5'), ['query' => 'test']);
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

        self::request(new JsonMockResponse(['message' => "model 'rerank-z' not found"], ['http_code' => 404]));
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

    public function testItConvertsResponseToRerankingResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'results' => [
                ['index' => 0, 'relevance_score' => 0.95],
                ['index' => 1, 'relevance_score' => 0.42],
            ],
        ]);

        $converter = new RerankClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(RerankingResult::class, $result);
        $entries = $result->getContent();
        $this->assertCount(2, $entries);
        $this->assertSame(0, $entries[0]->getIndex());
        $this->assertSame(0.95, $entries[0]->getScore());
        $this->assertSame(1, $entries[1]->getIndex());
        $this->assertSame(0.42, $entries[1]->getScore());
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainResults()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['invalid' => 'response']);

        $converter = new RerankClient(new MockHttpClient(), 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain reranking results.');

        $converter->convert(new RawHttpResult($response));
    }

    public function testGetTokenUsageExtractor()
    {
        $converter = new RerankClient(new MockHttpClient(), 'test-key');

        $this->assertInstanceOf(MetaBilledUnitsTokenUsageExtractor::class, $converter->getTokenUsageExtractor());
    }

    private static function request(MockResponse $response): void
    {
        $client = new RerankClient(new MockHttpClient($response), 'test-key');
        $client->convert($client->request(new Reranker('rerank-v3.5'), ['query' => 'What is AI?', 'texts' => ['Doc 1']]));
    }
}
