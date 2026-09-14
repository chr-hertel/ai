<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Transport;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpTransportTest extends TestCase
{
    public function testItThrowsExceptionWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new HttpTransport(new MockHttpClient(), '');
    }

    #[TestWith(['api-key-without-prefix'])]
    #[TestWith(['pk-api-key'])]
    #[TestWith(['SK-api-key'])]
    #[TestWith(['skapikey'])]
    #[TestWith(['sk api-key'])]
    #[TestWith(['sk'])]
    public function testItThrowsExceptionWhenApiKeyDoesNotStartWithSk(string $invalidApiKey)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must start with "sk-".');

        new HttpTransport(new MockHttpClient(), $invalidApiKey);
    }

    public function testItThrowsExceptionOnInvalidRegion()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid region "APAC".');

        new HttpTransport(new MockHttpClient(), 'sk-api-key', 'APAC');
    }

    public function testItThrowsAuthenticationExceptionOnUnauthorized()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Incorrect API key provided.');

        self::send(new JsonMockResponse(['error' => ['message' => 'Incorrect API key provided.']], ['http_code' => 401]));
    }

    public function testItThrowsBadRequestExceptionOnBadRequest()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid value for "temperature".');

        self::send(new JsonMockResponse(['error' => ['message' => 'Invalid value for "temperature".']], ['http_code' => 400]));
    }

    /**
     * @param array{code?: string, message: string} $error
     */
    #[TestWith([['code' => 'context_length_exceeded', 'message' => 'Context length exceeded for this request.']])]
    #[TestWith([['message' => 'Your input exceeds the context window of this model. Please decrease the length of your messages.']])]
    public function testItThrowsExceedContextSizeExceptionOnContextOverflow(array $error)
    {
        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage($error['message']);

        self::send(new JsonMockResponse(['error' => $error], ['http_code' => 400]));
    }

    public function testItThrowsModelNotFoundExceptionOnNotFound()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('The model "gpt-0" does not exist or you do not have access to it.');

        self::send(new JsonMockResponse(['error' => ['message' => 'The model "gpt-0" does not exist or you do not have access to it.', 'code' => 'model_not_found']], ['http_code' => 404]));
    }

    public function testItThrowsModelNotFoundExceptionOnNotFoundWithoutBody()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Not Found');

        self::send(new MockResponse('', ['http_code' => 404]));
    }

    /**
     * @param array<string, string> $headers
     */
    #[TestWith([['x-ratelimit-reset-requests' => '20s'], 20])]
    #[TestWith([['x-ratelimit-reset-tokens' => '2m30s'], 150])]
    #[TestWith([['retry-after' => '60'], 60])]
    #[TestWith([[], null])]
    public function testItThrowsRateLimitExceededExceptionWithRetryAfter(array $headers, ?int $expectedRetryAfter)
    {
        try {
            self::send(new JsonMockResponse(['error' => ['message' => 'Rate limit reached for requests']], ['http_code' => 429, 'response_headers' => $headers]));
            $this->fail('Expected a RateLimitExceededException to be thrown.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame($expectedRetryAfter, $e->getRetryAfter());
            $this->assertSame('Rate limit exceeded. Rate limit reached for requests', $e->getMessage());
        }
    }

    public function testItThrowsServerExceptionOnServerError()
    {
        $this->expectException(ServerException::class);

        self::send(new JsonMockResponse(['error' => ['message' => 'The server had an error while processing your request.']], ['http_code' => 503]));
    }

    public function testItLeavesOtherStatusesToTheClient()
    {
        $result = self::send(new MockResponse('Forbidden', ['http_code' => 403]));

        $this->assertInstanceOf(RawHttpResult::class, $result);
        $this->assertSame(403, $result->getObject()->getStatusCode());
    }

    private static function send(MockResponse $response): RawResultInterface
    {
        $transport = new HttpTransport(new MockHttpClient($response), 'sk-api-key');
        $result = $transport->send('/v1/responses', ['input' => []]);
        $transport->throwOnError($result);

        return $result;
    }
}
