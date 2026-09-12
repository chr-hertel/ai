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

use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAI HTTP transport.
 *
 * Owns Bearer-token auth, region-aware base URL selection, and protocol-level
 * error mapping (401 / 400 / 429 → typed exceptions). The 429 handler also
 * understands OpenAI's `x-ratelimit-reset-*` headers in addition to the
 * standard `Retry-After`.
 *
 * Handlers can opt out of JSON serialization by emitting an envelope with a
 * `Content-Type` header other than `application/json`; in that case the
 * payload is forwarded as a multipart body (used by Whisper transcription).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport implements TransportInterface
{
    use JsonBodyEncodingTrait;

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        ?string $region = null,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = self::resolveBaseUrl($region);
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $headers = $request->getHeaders();
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? 'application/json';

        $httpOptions = [
            'auth_bearer' => $this->apiKey,
            'headers' => $headers,
        ];

        if (null !== $body = $request->getBody()) {
            // Pre-encoded by the client (e.g. multipart advertising an image MIME type).
            $httpOptions['body'] = $body;
        } elseif ('multipart/form-data' === $contentType) {
            // Symfony HttpClient builds the multipart body automatically when
            // `body` is an array; the Content-Type header must NOT be sent
            // explicitly because the boundary is generated at request time.
            unset($httpOptions['headers']['Content-Type'], $httpOptions['headers']['content-type']);
            $httpOptions['body'] = $request->getPayload();
        } else {
            $httpOptions['headers']['Content-Type'] = 'application/json';
            $httpOptions['body'] = $this->encodeJsonBody($request->getPayload());
        }

        $response = $this->httpClient->request($request->getMethod(), $this->baseUrl.$request->getPath(), $httpOptions);

        $status = $response->getStatusCode();

        if (401 === $status) {
            throw new AuthenticationException(self::extractErrorMessage($response) ?? 'Unauthorized');
        }

        if (400 === $status) {
            $errorMessage = self::extractErrorMessage($response) ?? 'Bad Request';

            if ('context_length_exceeded' === self::extractErrorCode($response)) {
                throw new ExceedContextSizeException($errorMessage);
            }

            throw new BadRequestException($errorMessage);
        }

        if (429 === $status) {
            throw new RateLimitExceededException(self::extractRetryAfter($response), self::extractErrorMessage($response));
        }

        if ($status >= 500) {
            throw new ServerException($status, self::extractErrorMessage($response));
        }

        return new RawHttpResult($response);
    }

    private static function resolveBaseUrl(?string $region): string
    {
        return match ($region) {
            null => 'https://api.openai.com',
            Factory::REGION_EU => 'https://eu.api.openai.com',
            Factory::REGION_US => 'https://us.api.openai.com',
            default => throw new InvalidArgumentException(\sprintf('Invalid region "%s". Valid options are: "%s", "%s", or null.', $region, Factory::REGION_EU, Factory::REGION_US)),
        };
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
