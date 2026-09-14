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
use Symfony\AI\Platform\Bridge\Fireworks\EmbeddingsClient;
use Symfony\AI\Platform\Bridge\Fireworks\Factory;
use Symfony\AI\Platform\Bridge\Fireworks\Fireworks;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class EmbeddingsClientTest extends TestCase
{
    public function testSupportsEmbeddingsFireworksModel()
    {
        $client = self::createClient(new MockHttpClient());

        $this->assertTrue($client->supports(new Fireworks('accounts/fireworks/models/qwen3-embedding-8b', [Capability::EMBEDDINGS])));
    }

    public function testDoesNotSupportOtherModels()
    {
        $client = self::createClient(new MockHttpClient());

        $this->assertFalse($client->supports(new Model('gpt-4')));
        $this->assertFalse($client->supports(new Fireworks('accounts/fireworks/models/kimi-k2p6', [Capability::INPUT_MESSAGES])));
    }

    public function testRequestSendsEmbeddingsToCorrectEndpoint()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            self::assertSame('POST', $method);
            self::assertSame('https://api.fireworks.ai/inference/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);

            $body = json_decode($options['body'], true);
            self::assertSame('accounts/fireworks/models/qwen3-embedding-8b', $body['model']);
            self::assertSame('text to embed', $body['input']);

            return new JsonMockResponse(['data' => [['embedding' => [0.1, 0.2]]]]);
        });

        $model = new Fireworks('accounts/fireworks/models/qwen3-embedding-8b', [Capability::INPUT_TEXT, Capability::EMBEDDINGS]);

        self::createClient($httpClient)->request($model, 'text to embed');
        $this->assertTrue($requestMade);
    }

    public function testConvertEmbeddingsResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'data' => [
                ['index' => 0, 'embedding' => [0.3, 0.4, 0.4]],
                ['index' => 1, 'embedding' => [0.0, 0.0, 0.2]],
            ],
        ]));

        $result = self::createClient($httpClient)->convert(new RawHttpResult($httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/embeddings')));

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertSame([0.3, 0.4, 0.4], $result->getContent()[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $result->getContent()[1]->getData());
    }

    private static function createClient(HttpClientInterface $httpClient): EmbeddingsClient
    {
        return new EmbeddingsClient(new HttpTransport($httpClient, Factory::DEFAULT_INFERENCE_ENDPOINT, 'test-api-key'));
    }
}
