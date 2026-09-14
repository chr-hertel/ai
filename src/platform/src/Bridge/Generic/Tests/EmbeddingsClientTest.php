<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsClient;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbeddingsClientTest extends TestCase
{
    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = new EmbeddingsClient(new HttpTransport(new MockHttpClient(), 'http://localhost:8000'));

        $this->assertTrue($modelClient->supports(new EmbeddingsModel('text-embedding-3-small')));
    }

    public function testItIsNotSupportingTheIncorrectModel()
    {
        $modelClient = new EmbeddingsClient(new HttpTransport(new MockHttpClient(), 'http://localhost:8000'));

        $this->assertFalse($modelClient->supports(new CompletionsModel('gpt-4o-mini')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:8000/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"text-embedding-3-small","input":"test text"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new EmbeddingsClient(new HttpTransport($httpClient, 'http://localhost:8000', 'sk-api-key'));
        $modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test text');
    }

    public function testItIsExecutingTheCorrectRequestWithCustomOptions()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:8000/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"dimensions":256,"model":"text-embedding-3-large","input":"test text"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new EmbeddingsClient(new HttpTransport($httpClient, 'http://localhost:8000', 'sk-api-key'));
        $modelClient->request(new EmbeddingsModel('text-embedding-3-large'), 'test text', ['dimensions' => 256]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayInput()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:8000/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"text-embedding-3-small","input":["text1","text2","text3"]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new EmbeddingsClient(new HttpTransport($httpClient, 'http://localhost:8000', 'sk-api-key'));
        $modelClient->request(new EmbeddingsModel('text-embedding-3-small'), ['text1', 'text2', 'text3']);
    }

    #[TestWith(['https://api.inference.eu', 'https://api.inference.eu/v1/embeddings'])]
    #[TestWith(['https://api.inference.com', 'https://api.inference.com/v1/embeddings'])]
    public function testItUsesCorrectBaseUrl(string $baseUrl, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new EmbeddingsClient(new HttpTransport($httpClient, $baseUrl, 'sk-api-key'));
        $modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test input');
    }

    #[TestWith(['/custom/path', 'https://api.inference.com/custom/path'])]
    #[TestWith(['/v1/alternative/endpoint', 'https://api.inference.com/v1/alternative/endpoint'])]
    public function testsItUsesCorrectPathIfProvided(string $path, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new EmbeddingsClient(new HttpTransport($httpClient, 'https://api.inference.com', 'sk-api-key'), $path);
        $modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test input');
    }

    public function testItConvertsAResponseToAVectorResult()
    {
        $result = $this->createStub(HttpResponse::class);
        $result
            ->method('toArray')
            ->willReturn(json_decode($this->getEmbeddingStub(), true));

        $vectorResult = self::client()->convert(new RawHttpResult($result));
        $convertedContent = $vectorResult->getContent();

        $this->assertCount(2, $convertedContent);

        $this->assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
    }

    public function testItConvertsAnEmptyDataListToAnEmptyVectorResult()
    {
        $vectorResult = self::client()->convert(new InMemoryRawResult(['object' => 'list', 'data' => []]));

        $this->assertSame([], $vectorResult->getContent());
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainData()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain data.');

        self::client()->convert(new InMemoryRawResult(['invalid' => 'response']));
    }

    public function testThrowsRateLimitExceededExceptionWithRetryAfterHeader()
    {
        $httpResponse = new MockResponse('{"error":{"message":"You exceeded your current quota, please check your plan and billing details."}}', ['http_code' => 429, 'response_headers' => ['retry-after' => '60']]);
        $modelClient = self::client($httpResponse);

        $exception = null;
        try {
            $modelClient->convert($modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test input'));
        } catch (RateLimitExceededException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertSame(60, $exception->getRetryAfter());
        $this->assertSame('Rate limit exceeded. You exceeded your current quota, please check your plan and billing details.', $exception->getMessage());
    }

    public function testThrowsRateLimitExceededExceptionWithoutRetryAfterHeader()
    {
        $httpResponse = new MockResponse('', ['http_code' => 429]);
        $modelClient = self::client($httpResponse);

        $exception = null;
        try {
            $modelClient->convert($modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test input'));
        } catch (RateLimitExceededException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertNull($exception->getRetryAfter());
        $this->assertSame('Rate limit exceeded.', $exception->getMessage());
    }

    public function testThrowsServerExceptionOnServerError()
    {
        $modelClient = self::client(new MockResponse('{"error":{"message":"Service unavailable."}}', ['http_code' => 503]));

        $exception = null;
        try {
            $modelClient->convert($modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test input'));
        } catch (ServerException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertSame(503, $exception->getStatusCode());
        $this->assertSame('Server error (HTTP 503). Service unavailable.', $exception->getMessage());
    }

    public function testThrowsServerExceptionOnServerErrorWithoutJsonBody()
    {
        $modelClient = self::client(new MockResponse('<html>Bad Gateway</html>', ['http_code' => 502]));

        $exception = null;
        try {
            $modelClient->convert($modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test input'));
        } catch (ServerException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertSame(502, $exception->getStatusCode());
        $this->assertSame('Server error (HTTP 502).', $exception->getMessage());
    }

    public function testThrowsAuthenticationExceptionWithoutJsonBody()
    {
        $modelClient = self::client(new MockResponse('Unauthorized', ['http_code' => 401]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authentication failed.');

        $modelClient->convert($modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test input'));
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('provideTopLevelErrorMessages')]
    public function testThrowsWithTheErrorMessageFromATopLevelMessage(int $statusCode, string $message, string $exception)
    {
        $modelClient = self::client(new JsonMockResponse(['message' => $message, 'type' => 'invalid_request_error'], ['http_code' => $statusCode]));

        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        $modelClient->convert($modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test input'));
    }

    /**
     * @return iterable<string, array{int, string, class-string<\Throwable>}>
     */
    public static function provideTopLevelErrorMessages(): iterable
    {
        yield 'unauthorized' => [401, 'Invalid credentials in Authorization header', AuthenticationException::class];
        yield 'bad request' => [400, 'Input must not be empty', BadRequestException::class];
        yield 'not found' => [404, 'Model "some-embeddings" not found', ModelNotFoundException::class];
        yield 'rate limit' => [429, 'Rate limit reached, try again later', RateLimitExceededException::class];
        yield 'server error' => [503, 'Model is overloaded', ServerException::class];
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('provideNonStringErrorMessages')]
    public function testFallsBackToTheDefaultMessageOnANonStringErrorMessage(int $statusCode, string $body, string $exception, string $message)
    {
        $modelClient = self::client(new MockResponse($body, ['http_code' => $statusCode]));

        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        $modelClient->convert($modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test input'));
    }

    /**
     * @return iterable<string, array{int, string, class-string<\Throwable>, string}>
     */
    public static function provideNonStringErrorMessages(): iterable
    {
        yield 'unauthorized' => [401, '{"message":["a","b"]}', AuthenticationException::class, 'Authentication failed.'];
        yield 'bad request' => [400, '{"message":{"detail":[{"msg":"x"}]}}', BadRequestException::class, 'Bad Request'];
        yield 'not found' => [404, '{"error":{"message":["a","b"]}}', ModelNotFoundException::class, 'Not Found'];
        yield 'rate limit' => [429, '{"message":["a","b"]}', RateLimitExceededException::class, 'Rate limit exceeded.'];
        yield 'server error' => [503, '{"error":{"message":{"detail":[{"msg":"x"}]}}}', ServerException::class, 'Server error (HTTP 503).'];
    }

    private function getEmbeddingStub(): string
    {
        return <<<'JSON'
            {
              "object": "list",
              "data": [
                {
                  "object": "embedding",
                  "index": 0,
                  "embedding": [0.3, 0.4, 0.4]
                },
                {
                  "object": "embedding",
                  "index": 1,
                  "embedding": [0.0, 0.0, 0.2]
                }
              ]
            }
            JSON;
    }

    private static function client(?MockResponse $response = null): EmbeddingsClient
    {
        return new EmbeddingsClient(new HttpTransport(new MockHttpClient($response), 'http://localhost:8000'));
    }
}
