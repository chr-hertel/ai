<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransportTest extends TestCase
{
    public function testThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $transport = new HttpTransport(new MockHttpClient(new JsonMockResponse([
            'error' => [
                'code' => 400,
                'status' => 'INVALID_ARGUMENT',
                'message' => 'The input token count (1294145) exceeds the maximum number of tokens allowed (1048576).',
            ],
        ], ['http_code' => 400])), 'test-key');

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('exceeds the maximum number of tokens allowed');

        $transport->throwOnError($transport->send('/models/gemini-2.5-flash:generateContent', []));
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('https://gemini.example.com/v1beta/models/gemini-1.5-flash:generateContent', $url);

            return new JsonMockResponse(['candidates' => []]);
        });

        (new HttpTransport($httpClient, 'test-api-key', 'https://gemini.example.com/'))->send('models/gemini-1.5-flash:generateContent', ['contents' => []]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new JsonMockResponse(['candidates' => []]);
        });

        (new HttpTransport($httpClient, 'test-api-key'))->send('models/gemini-1.5-flash:generateContent', ['contents' => [['parts' => [['text' => "tool output \xB1 here"]]]]]);
    }

    public function testRateLimitExceededThrowsException()
    {
        $transport = new HttpTransport(new MockHttpClient([
            new MockResponse('{"error":{"code":429,"message":"Resource has been exhausted (e.g. check quota).","status":"RESOURCE_EXHAUSTED"}}', [
                'http_code' => 429,
            ]),
        ]), 'test-key');

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded. Resource has been exhausted (e.g. check quota).');

        try {
            $transport->throwOnError($transport->send('models/gemini-pro:generateContent', []));
        } catch (RateLimitExceededException $e) {
            $this->assertNull($e->getRetryAfter());
            throw $e;
        }
    }

    public function testThrowsServerExceptionOnServerErrorStatus()
    {
        $transport = new HttpTransport(new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 500])), 'test-key');

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        $transport->throwOnError($transport->send('/models/gemini-2.5-flash:generateContent', []));
    }
}
