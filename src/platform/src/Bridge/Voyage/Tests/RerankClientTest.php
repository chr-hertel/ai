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
use Symfony\AI\Platform\Bridge\Voyage\Factory;
use Symfony\AI\Platform\Bridge\Voyage\RerankClient;
use Symfony\AI\Platform\Bridge\Voyage\Reranker;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
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

        $this->assertTrue($client->supports(new Reranker('rerank-2.5')));
    }

    public function testItDoesNotSupportVoyageModel()
    {
        $client = new RerankClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Voyage('voyage-3')));
    }

    public function testTheFactoryDispatchesRerankerModelsToThisClient()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): JsonMockResponse {
            $requestedUrl = $url;

            return new JsonMockResponse(['data' => [['index' => 0, 'relevance_score' => 0.95]], 'usage' => ['total_tokens' => 18]]);
        });

        $result = Factory::createPlatform('test-key', $httpClient)
            ->invoke('rerank-2.5', ['query' => 'What is AI?', 'texts' => ['Document about AI']])
            ->getResult();

        $this->assertSame('https://api.voyageai.com/v1/rerank', $requestedUrl);
        $this->assertInstanceOf(RerankingResult::class, $result);
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.voyageai.com/v1/rerank', $url);

            $body = json_decode($options['body'], true);
            $this->assertSame('rerank-2.5', $body['model']);
            $this->assertSame('What is AI?', $body['query']);
            $this->assertSame(['Document about AI', 'Document about cooking'], $body['documents']);
            $this->assertArrayNotHasKey('top_k', $body);

            return new MockResponse();
        }]);

        $client = new RerankClient($httpClient, 'test-key');

        $client->request(new Reranker('rerank-2.5'), [
            'query' => 'What is AI?',
            'texts' => ['Document about AI', 'Document about cooking'],
        ]);
    }

    public function testItSendsTopKOption()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame(3, $body['top_k']);

            return new MockResponse();
        }]);

        $client = new RerankClient($httpClient, 'test-key');

        $client->request(new Reranker('rerank-2.5'), [
            'query' => 'What is AI?',
            'texts' => ['Doc 1', 'Doc 2', 'Doc 3', 'Doc 4'],
        ], ['top_k' => 3]);
    }

    public function testItThrowsExceptionForStringPayload()
    {
        $client = new RerankClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reranker payload must be an array with "query" and "texts" keys.');

        $client->request(new Reranker('rerank-2.5'), 'invalid string payload');
    }

    public function testItThrowsExceptionForMissingQueryKey()
    {
        $client = new RerankClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reranker payload must be an array with "query" and "texts" keys.');

        $client->request(new Reranker('rerank-2.5'), ['texts' => ['doc1']]);
    }

    public function testItThrowsExceptionForMissingTextsKey()
    {
        $client = new RerankClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reranker payload must be an array with "query" and "texts" keys.');

        $client->request(new Reranker('rerank-2.5'), ['query' => 'test']);
    }

    public function testItThrowsExceptionOnNon200StatusCode()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getContent')->willReturn('Internal Server Error');

        $converter = new RerankClient(new MockHttpClient(), 'test-key');

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItReadsTheDetailOfAnErrorResponse()
    {
        $response = (new MockHttpClient(new MockResponse('{"detail":"Provided API key is invalid."}', ['http_code' => 401])))->request('POST', 'https://api.voyageai.com/v1/rerank');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Provided API key is invalid.');

        (new RerankClient(new MockHttpClient(), 'test-key'))->convert(new RawHttpResult($response));
    }

    public function testItConvertsResponseToRerankingResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'object' => 'list',
            'data' => [
                ['index' => 0, 'relevance_score' => 0.95],
                ['index' => 1, 'relevance_score' => 0.42],
            ],
            'model' => 'rerank-2.5',
            'usage' => ['total_tokens' => 18],
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
}
