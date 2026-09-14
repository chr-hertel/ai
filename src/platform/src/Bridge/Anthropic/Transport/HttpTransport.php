<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Transport;

use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
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

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of an Anthropic-compatible endpoint, without trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.anthropic.com',
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function send(Model $model, string $path, array $payload, array $headers = []): RawResultInterface
    {
        $headers = array_merge([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ], $headers);

        $response = $this->httpClient->request('POST', $this->baseUrl.$path, [
            'headers' => $headers,
            'body' => $this->encodeJsonBody($payload),
        ]);

        return new RawHttpResult($response);
    }

    public function throwOnError(RawResultInterface $result, array $options = []): void
    {
        if (!$result instanceof RawHttpResult) {
            return;
        }

        $response = $result->getObject();

        if (401 === $response->getStatusCode()) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? 'Unauthorized';
            throw new AuthenticationException($errorMessage);
        }

        if (400 === $response->getStatusCode()) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? 'Bad Request';

            if (str_contains($errorMessage, 'prompt is too long')) {
                throw new ExceedContextSizeException($errorMessage);
            }

            throw new BadRequestException($errorMessage);
        }

        if (429 === $response->getStatusCode()) {
            $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
            $retryAfterValue = $retryAfter ? (int) $retryAfter : null;
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? null;
            throw new RateLimitExceededException($retryAfterValue, $errorMessage);
        }

        if (($code = $response->getStatusCode()) >= 500) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? null;
            throw new ServerException($code, $errorMessage);
        }

        if (($options['stream'] ?? false) && ($code = $response->getStatusCode()) >= 400) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $code, $response->getContent(false)));
        }
    }
}
