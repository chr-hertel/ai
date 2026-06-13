<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings\EndpointHandler;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EndpointHandlerTest extends TestCase
{
    public function testItSupportsTheCorrectModel()
    {
        $handler = new EndpointHandler(new MockHttpClient(), 'sk-api-key');

        $this->assertTrue($handler->supports(new Embeddings('text-embedding-3-small')));
    }

    public function testItPerformsRequestAndConvertsResult()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"text-embedding-3-small","input":"test text"}', $options['body']);

            return new MockResponse(json_encode([
                'data' => [['embedding' => [0.1, 0.2, 0.3]]],
                'usage' => ['prompt_tokens' => 4, 'total_tokens' => 4],
            ]));
        };

        $handler = new EndpointHandler(new MockHttpClient([$resultCallback]), 'sk-api-key');
        $result = $handler->request(new Embeddings('text-embedding-3-small'), 'test text');

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertSame([0.1, 0.2, 0.3], $result->getContent()[0]->getData());
        $this->assertInstanceOf(TokenUsageInterface::class, $result->getMetadata()->get('token_usage'));
    }
}
