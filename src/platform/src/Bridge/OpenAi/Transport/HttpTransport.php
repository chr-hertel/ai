<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Transport;

use Symfony\AI\Platform\Bridge\OpenAi\RegionAwareTrait;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport implements TransportInterface
{
    use JsonBodyEncodingTrait;
    use RegionAwareTrait;

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        ?string $region = null,
    ) {
        self::validateApiKey($apiKey);

        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = self::getBaseUrl($region);
    }

    public function send(string $path, array|string $body, array $headers = []): RawResultInterface
    {
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? 'application/json';

        $httpOptions = [
            'auth_bearer' => $this->apiKey,
            'headers' => $headers,
        ];

        if (\is_string($body)) {
            $httpOptions['body'] = $body;
        } elseif ('multipart/form-data' === $contentType) {
            // HttpClient sets the multipart Content-Type itself, boundary included.
            unset($httpOptions['headers']['Content-Type'], $httpOptions['headers']['content-type']);
            $httpOptions['body'] = $body;
        } else {
            $httpOptions['headers']['Content-Type'] = 'application/json';
            $httpOptions['body'] = $this->encodeJsonBody($body);
        }

        $response = $this->httpClient->request('POST', $this->baseUrl.$path, $httpOptions);

        return new RawHttpResult($response);
    }

    public function throwOnError(RawResultInterface $result, array $options = []): void
    {
        if (!$result instanceof RawHttpResult) {
            return;
        }

        $response = $result->getObject();
        $status = $response->getStatusCode();

        if (401 === $status) {
            throw new AuthenticationException(self::extractErrorMessage($response) ?? 'Unauthorized');
        }

        if (400 === $status) {
            $errorMessage = self::extractErrorMessage($response) ?? 'Bad Request';

            if ('context_length_exceeded' === self::extractErrorCode($response) || str_contains($errorMessage, 'exceeds the context window')) {
                throw new ExceedContextSizeException($errorMessage);
            }

            throw new BadRequestException($errorMessage);
        }

        if (404 === $status) {
            throw new ModelNotFoundException(self::extractErrorMessage($response) ?? 'Not Found');
        }

        if (429 === $status) {
            throw new RateLimitExceededException(self::extractRetryAfter($response), self::extractErrorMessage($response));
        }

        if ($status >= 500) {
            throw new ServerException($status, self::extractErrorMessage($response));
        }
    }

    private static function extractErrorMessage(\Symfony\Contracts\HttpClient\ResponseInterface $response): ?string
    {
        $body = $response->getContent(false);
        $decoded = json_decode($body, true);

        if (!\is_array($decoded)) {
            return null;
        }

        return $decoded['error']['message'] ?? $decoded['message'] ?? null;
    }

    private static function extractErrorCode(\Symfony\Contracts\HttpClient\ResponseInterface $response): ?string
    {
        $decoded = json_decode($response->getContent(false), true);

        if (!\is_array($decoded)) {
            return null;
        }

        return $decoded['error']['code'] ?? null;
    }

    private static function extractRetryAfter(\Symfony\Contracts\HttpClient\ResponseInterface $response): ?int
    {
        $headers = $response->getHeaders(false);

        $resetTime = $headers['x-ratelimit-reset-requests'][0]
            ?? $headers['x-ratelimit-reset-tokens'][0]
            ?? $headers['retry-after'][0]
            ?? null;

        if (null === $resetTime) {
            return null;
        }

        if (ctype_digit($resetTime)) {
            return (int) $resetTime;
        }

        // OpenAI format: "1s", "6m0s", "2m30s"
        if (preg_match('/^(?:(\d+)m)?(?:(\d+)s)?$/', $resetTime, $matches)) {
            $minutes = isset($matches[1]) ? (int) $matches[1] : 0;
            $secs = isset($matches[2]) ? (int) $matches[2] : 0;

            return ($minutes * 60) + $secs;
        }

        return null;
    }
}
