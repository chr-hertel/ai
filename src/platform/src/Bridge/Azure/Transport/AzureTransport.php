<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Transport;

use Symfony\AI\Platform\Bridge\Azure\BaseUrlNormalizerTrait;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\TransportInterface;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AzureTransport implements TransportInterface
{
    use BaseUrlNormalizerTrait;
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    private const V1_PATHS = ['/v1/responses'];

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of the Azure resource; accepts a bare host (https assumed) or a
     *                        full URL with scheme, with or without a trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
        private readonly string $deployment,
        private readonly string $apiVersion,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        if ('' === $baseUrl) {
            throw new InvalidArgumentException('The base URL must not be empty.');
        }

        if ('' === $deployment) {
            throw new InvalidArgumentException('The deployment must not be empty.');
        }

        if ('' === $apiVersion) {
            throw new InvalidArgumentException('The API version must not be empty.');
        }

        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }

        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
    }

    public function send(string $path, array|string $body, array $headers = []): RawResultInterface
    {
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? 'application/json';
        $isV1Path = \in_array($path, self::V1_PATHS, true);

        if ($isV1Path && \is_array($body)) {
            $body['model'] = $this->deployment;
        }

        $httpOptions = [
            'headers' => array_merge($headers, ['api-key' => $this->apiKey]),
        ];

        if (!$isV1Path) {
            $httpOptions['query'] = ['api-version' => $this->apiVersion];
        }

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

        $response = $this->httpClient->request('POST', $this->resolveUrl($path), $httpOptions);

        return new RawHttpResult($response);
    }

    public function throwOnError(RawResultInterface $result, array $options = []): void
    {
        if (!$result instanceof RawHttpResult) {
            return;
        }

        $response = $result->getObject();

        if (400 === $response->getStatusCode() && $this->exceedsContextSize($response)) {
            throw new ExceedContextSizeException($this->extractErrorMessage($response) ?? 'Bad Request');
        }

        $this->throwOnHttpError($response);
    }

    private function exceedsContextSize(ResponseInterface $response): bool
    {
        $error = json_decode($response->getContent(false), true)['error'] ?? null;

        if (!\is_array($error)) {
            return false;
        }

        return 'context_length_exceeded' === ($error['code'] ?? null)
            || str_contains($error['message'] ?? '', 'exceeds the context window');
    }

    private function resolveUrl(string $path): string
    {
        if (\in_array($path, self::V1_PATHS, true)) {
            return $this->baseUrl.'/openai'.$path;
        }

        return \sprintf('%s/openai/deployments/%s/%s', $this->baseUrl, $this->deployment, ltrim(substr($path, \strlen('/v1')), '/'));
    }
}
