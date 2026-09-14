<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\BatchEmbedContentsClient;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings;
use Symfony\AI\Platform\Bridge\Gemini\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class BatchEmbedContentsClientTest extends TestCase
{
    private const EMBEDDINGS = [
        'embeddings' => [
            ['values' => [0.3, 0.4, 0.4]],
            ['values' => [0.0, 0.0, 0.2]],
        ],
    ];

    public function testItMakesARequestWithCorrectPayload()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('POST', $method);
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-exp-03-07:batchEmbedContents', $url);
            $this->assertContains('x-goog-api-key: test', $options['headers']);
            $this->assertSame([
                'requests' => [
                    [
                        'model' => 'models/gemini-embedding-exp-03-07',
                        'content' => ['parts' => [['text' => 'payload1']]],
                        'outputDimensionality' => 1536,
                        'taskType' => 'CLASSIFICATION',
                    ],
                    [
                        'model' => 'models/gemini-embedding-exp-03-07',
                        'content' => ['parts' => [['text' => 'payload2']]],
                        'outputDimensionality' => 1536,
                        'taskType' => 'CLASSIFICATION',
                    ],
                ],
            ], json_decode($options['body'], true));

            return new JsonMockResponse(self::EMBEDDINGS);
        });

        $model = new Embeddings('gemini-embedding-exp-03-07', options: ['dimensions' => 1536, 'task_type' => 'CLASSIFICATION']);

        $result = (new BatchEmbedContentsClient(new HttpTransport($httpClient, 'test')))->request($model, ['payload1', 'payload2']);
        $this->assertSame(self::EMBEDDINGS, $result->getData());
    }

    public function testItConvertsAResponseToAVectorResult()
    {
        $vectorResult = self::client()->convert(new InMemoryRawResult(self::EMBEDDINGS));
        $convertedContent = $vectorResult->getContent();

        $this->assertCount(2, $convertedContent);

        $this->assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
    }

    public function testItThrowsWhenTheResponseContainsNoEmbeddings()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain data.');

        self::client()->convert(new InMemoryRawResult([]));
    }

    private static function client(): BatchEmbedContentsClient
    {
        return new BatchEmbedContentsClient(new HttpTransport(new MockHttpClient(), 'test'));
    }
}
