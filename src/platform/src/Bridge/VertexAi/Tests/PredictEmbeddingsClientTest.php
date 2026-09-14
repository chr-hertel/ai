<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\Model;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\TaskType;
use Symfony\AI\Platform\Bridge\VertexAi\PredictEmbeddingsClient;
use Symfony\AI\Platform\Bridge\VertexAi\Transport\VertexAiTransport;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class PredictEmbeddingsClientTest extends TestCase
{
    public function testItGeneratesTheEmbeddingSuccessfully()
    {
        $expectedResponse = [
            'predictions' => [
                ['embeddings' => ['values' => [0.3, 0.4, 0.4]]],
            ],
        ];
        $httpClient = new MockHttpClient(new JsonMockResponse($expectedResponse));

        $client = new PredictEmbeddingsClient(new VertexAiTransport($httpClient, 'global', 'test'));

        $model = new Model('gemini-embedding-001', options: ['outputDimensionality' => 1536, 'task_type' => TaskType::CLASSIFICATION]);

        $result = $client->request($model, 'test payload');

        $this->assertInstanceOf(RawHttpResult::class, $result);
        $this->assertSame($expectedResponse, $result->getData());
        $this->assertSame(
            'https://aiplatform.googleapis.com/v1/projects/test/locations/global/publishers/google/models/gemini-embedding-001:predict',
            $result->getObject()->getInfo()['url'],
        );
    }

    public function testItUsesTheRegionalEndpointForARegionalLocation()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame(
                'https://europe-west1-aiplatform.googleapis.com/v1/projects/test/locations/europe-west1/publishers/google/models/gemini-embedding-001:predict',
                $url,
            );

            return new JsonMockResponse(['predictions' => []]);
        });

        $client = new PredictEmbeddingsClient(new VertexAiTransport($httpClient, 'europe-west1', 'test'));
        $client->request(new Model('gemini-embedding-001'), 'test payload');
    }

    public function testItUsesTheResidencyEndpointForAJurisdictionalLocation()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame(
                'https://aiplatform.us.rep.googleapis.com/v1/projects/test/locations/us/publishers/google/models/gemini-embedding-001:predict',
                $url,
            );

            return new JsonMockResponse(['predictions' => []]);
        });

        $client = new PredictEmbeddingsClient(new VertexAiTransport($httpClient, 'us', 'test'));
        $client->request(new Model('gemini-embedding-001'), 'test payload');
    }

    public function testItUsesTheGlobalHostWhenNoLocationIsProvided()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertStringStartsWith(
                'https://aiplatform.googleapis.com/v1/publishers/google/models/gemini-embedding-001:predict',
                $url,
            );

            return new JsonMockResponse(['predictions' => []]);
        });

        $client = new PredictEmbeddingsClient(new VertexAiTransport($httpClient, apiKey: 'test-key'));
        $client->request(new Model('gemini-embedding-001'), 'test payload');
    }

    public function testItUsesTheGlobalEndpointWhenTheProjectIdIsMissing()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame(
                'https://aiplatform.googleapis.com/v1/publishers/google/models/gemini-embedding-001:predict',
                $url,
            );

            return new JsonMockResponse(['predictions' => []]);
        });

        $client = new PredictEmbeddingsClient(new VertexAiTransport($httpClient, 'europe-west1'));
        $client->request(new Model('gemini-embedding-001'), 'test payload');
    }

    public function testItSendsTheInstancesWithTheDefaultTaskTypeAndTheModelOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame([
                'instances' => [
                    ['content' => 'test payload', 'title' => 'Title', 'task_type' => TaskType::RETRIEVAL_QUERY],
                ],
                'outputDimensionality' => 1536,
            ], json_decode($options['body'], true));

            return new JsonMockResponse(['predictions' => []]);
        });

        $client = new PredictEmbeddingsClient(new VertexAiTransport($httpClient, 'global', 'test'));
        $client->request(new Model('gemini-embedding-001', options: ['outputDimensionality' => 1536]), ['test payload'], ['title' => 'Title']);
    }

    public function testItConvertsAResponseToAVectorResult()
    {
        $vectorResult = self::client()->convert(new InMemoryRawResult([
            'predictions' => [
                ['embeddings' => ['values' => [0.3, 0.4, 0.4]]],
                ['embeddings' => ['values' => [0.0, 0.0, 0.2]]],
            ],
        ]));
        $convertedContent = $vectorResult->getContent();

        $this->assertCount(2, $convertedContent);

        $this->assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
    }

    public function testItThrowsWithTheApiErrorMessageAndCode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error from Embeddings API: "Permission denied."');
        $this->expectExceptionCode(403);

        self::client()->convert(new InMemoryRawResult(['error' => ['code' => 403, 'message' => 'Permission denied.']]));
    }

    private static function client(): PredictEmbeddingsClient
    {
        return new PredictEmbeddingsClient(new VertexAiTransport(new MockHttpClient(), 'global', 'test'));
    }
}
