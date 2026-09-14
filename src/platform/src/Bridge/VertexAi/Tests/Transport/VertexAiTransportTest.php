<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Transport\VertexAiTransport;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VertexAiTransportTest extends TestCase
{
    public function testItPassesTheApiKeyAsQueryParameter()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame('https://aiplatform.googleapis.com/v1/publishers/google/models/gemini-2.0-flash:generateContent?key=test-key', $url);

            return new JsonMockResponse(['candidates' => []]);
        });

        (new VertexAiTransport($httpClient, apiKey: 'test-key'))->send('models/gemini-2.0-flash:generateContent', ['contents' => []]);
    }

    public function testItThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $transport = new VertexAiTransport(new MockHttpClient(new JsonMockResponse([
            'error' => [
                'code' => 400,
                'status' => 'INVALID_ARGUMENT',
                'message' => 'The input token count (1294145) exceeds the maximum number of tokens allowed (1048576).',
            ],
        ], ['http_code' => 400])), 'global', 'test');

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('exceeds the maximum number of tokens allowed');

        $transport->throwOnError($transport->send('models/gemini-2.0-flash:generateContent', []));
    }

    public function testItThrowsRateLimitExceededExceptionWithTheApiMessage()
    {
        $transport = new VertexAiTransport(new MockHttpClient(new JsonMockResponse([
            'error' => ['code' => 429, 'message' => 'Resource exhausted.', 'status' => 'RESOURCE_EXHAUSTED'],
        ], ['http_code' => 429])), 'global', 'test');

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Resource exhausted.');

        $transport->throwOnError($transport->send('models/gemini-2.0-flash:generateContent', []));
    }

    public function testItThrowsServerExceptionOnServerErrorStatus()
    {
        $transport = new VertexAiTransport(new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 500])), 'global', 'test');

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        $transport->throwOnError($transport->send('models/gemini-2.0-flash:streamGenerateContent?alt=sse', []));
    }
}
