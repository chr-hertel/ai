<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity\Tests\Transport;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Perplexity\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class HttpTransportTest extends TestCase
{
    public function testItThrowsExceptionWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new HttpTransport(new MockHttpClient(), 'https://api.perplexity.ai', '');
    }

    #[TestWith(['api-key-without-prefix'])]
    #[TestWith(['plx-api-key'])]
    #[TestWith(['PPLX-api-key'])]
    #[TestWith(['pplxapikey'])]
    #[TestWith(['pplx api-key'])]
    #[TestWith(['pplx'])]
    public function testItThrowsExceptionWhenApiKeyDoesNotStartWithPplx(string $invalidApiKey)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must start with "pplx-".');

        new HttpTransport(new MockHttpClient(), 'https://api.perplexity.ai', $invalidApiKey);
    }

    public function testItAcceptsValidApiKey()
    {
        $transport = new HttpTransport(new MockHttpClient(), 'https://api.perplexity.ai', 'pplx-valid-api-key');

        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    public function testItWrapsHttpClientInEventSourceHttpClient()
    {
        $httpClient = new MockHttpClient();
        $transport = new HttpTransport($httpClient, 'https://api.perplexity.ai', 'pplx-valid-api-key');

        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    public function testItAcceptsEventSourceHttpClientDirectly()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $transport = new HttpTransport($httpClient, 'https://api.perplexity.ai', 'pplx-valid-api-key');

        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.perplexity.ai/chat/completions', $url);
            self::assertSame('Authorization: Bearer pplx-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"sonar","messages":[{"role":"user","content":"test message"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $transport = new HttpTransport($httpClient, 'https://api.perplexity.ai', 'pplx-api-key');
        $transport->send('/chat/completions', ['model' => 'sonar', 'messages' => [['role' => 'user', 'content' => 'test message']]]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.perplexity.ai/chat/completions', $url);
            self::assertSame('Authorization: Bearer pplx-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"sonar","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $transport = new HttpTransport($httpClient, 'https://api.perplexity.ai', 'pplx-api-key');
        $transport->send('/chat/completions', ['model' => 'sonar', 'messages' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            self::assertJson($options['body']);
            self::assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $transport = new HttpTransport($httpClient, 'https://api.perplexity.ai', 'pplx-api-key');
        $transport->send('/chat/completions', ['model' => 'sonar', 'messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }

    public function testConvertThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => [
                'message' => 'The total length of all messages is too long.',
                'type' => 'too_many_prompt_tokens',
                'code' => 400,
            ],
        ], ['http_code' => 400]));

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('The total length of all messages is too long.');

        $transport = new HttpTransport($httpClient, 'https://api.perplexity.ai', 'pplx-api-key');
        $transport->throwOnError($transport->send('/chat/completions', []));
    }
}
