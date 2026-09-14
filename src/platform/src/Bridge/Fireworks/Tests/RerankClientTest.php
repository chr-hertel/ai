<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Fireworks\Factory;
use Symfony\AI\Platform\Bridge\Fireworks\Fireworks;
use Symfony\AI\Platform\Bridge\Fireworks\RerankClient;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class RerankClientTest extends TestCase
{
    public function testSupportsRerankingFireworksModel()
    {
        $client = self::createClient(new MockHttpClient());

        $this->assertTrue($client->supports(new Fireworks('accounts/fireworks/models/qwen3-reranker-8b', [Capability::RERANKING])));
    }

    public function testDoesNotSupportOtherModels()
    {
        $client = self::createClient(new MockHttpClient());

        $this->assertFalse($client->supports(new Model('gpt-4')));
        $this->assertFalse($client->supports(new Fireworks('accounts/fireworks/models/kimi-k2p6', [Capability::INPUT_MESSAGES])));
    }

    public function testRequestSendsRerankToCorrectEndpoint()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            self::assertSame('POST', $method);
            self::assertSame('https://api.fireworks.ai/inference/v1/rerank', $url);
            self::assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);

            $body = json_decode($options['body'], true);
            self::assertSame('accounts/fireworks/models/qwen3-reranker-8b', $body['model']);
            self::assertSame('What is AI?', $body['query']);
            self::assertSame(['AI is a field of study', 'Cooking is fun'], $body['documents']);

            return new JsonMockResponse(['data' => [['index' => 0, 'relevance_score' => 0.9]]]);
        });

        $model = new Fireworks('accounts/fireworks/models/qwen3-reranker-8b', [Capability::RERANKING]);

        self::createClient($httpClient)->request($model, ['query' => 'What is AI?', 'documents' => ['AI is a field of study', 'Cooking is fun']]);
        $this->assertTrue($requestMade);
    }

    public function testRequestThrowsOnStringPayload()
    {
        $model = new Fireworks('accounts/fireworks/models/qwen3-reranker-8b', [Capability::RERANKING]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rerank payload must be an array with "query" and "documents" keys.');

        self::createClient(new MockHttpClient())->request($model, 'invalid string payload');
    }

    public function testRequestThrowsOnMissingDocuments()
    {
        $model = new Fireworks('accounts/fireworks/models/qwen3-reranker-8b', [Capability::RERANKING]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rerank payload must be an array with "query" and "documents" keys.');

        self::createClient(new MockHttpClient())->request($model, ['query' => 'What is AI?']);
    }

    public function testConvertRerankResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'data' => [
                ['index' => 0, 'relevance_score' => 0.95],
                ['index' => 1, 'relevance_score' => 0.42],
            ],
        ]));

        $result = self::createClient($httpClient)->convert(new RawHttpResult($httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/rerank')));

        $this->assertInstanceOf(RerankingResult::class, $result);

        $entries = $result->getContent();
        $this->assertSame(0, $entries[0]->getIndex());
        $this->assertSame(0.95, $entries[0]->getScore());
        $this->assertSame(1, $entries[1]->getIndex());
        $this->assertSame(0.42, $entries[1]->getScore());
    }

    private static function createClient(HttpClientInterface $httpClient): RerankClient
    {
        return new RerankClient(new HttpTransport($httpClient, Factory::DEFAULT_INFERENCE_ENDPOINT, 'test-api-key'));
    }
}
