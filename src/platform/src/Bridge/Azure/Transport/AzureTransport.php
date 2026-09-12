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
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Azure OpenAI transport.
 *
 * Lets the OpenAI contract handlers run against an Azure resource unchanged:
 * everything that differs is a deployment concern owned here — `api-key`
 * auth instead of a bearer token, the resource base URL, the `api-version`
 * query parameter, and addressing a *deployment* rather than a model.
 *
 * Azure exposes two URL surfaces. The newer one mirrors OpenAI's paths
 * one-to-one under `/openai/v1/` and selects the deployment through the
 * request body; the classic one addresses each contract under
 * `/openai/deployments/{deployment}/` and takes `api-version`.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AzureTransport implements TransportInterface
{
    use BaseUrlNormalizerTrait;
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    /**
     * Contracts Azure serves through its newer "v1" surface, which mirrors
     * OpenAI's own paths and carries the deployment in the body.
     */
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

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $path = $request->getPath();
        $payload = $request->getPayload();
        $headers = $request->getHeaders();
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? 'application/json';
        $isV1Path = \in_array($path, self::V1_PATHS, true);

        if ($isV1Path) {
            // The v1 surface has no deployment in the URL, so the body names it.
            $payload['model'] = $this->deployment;
        }

        $httpOptions = [
            'headers' => array_merge($headers, ['api-key' => $this->apiKey]),
        ];

        if (!$isV1Path) {
            $httpOptions['query'] = ['api-version' => $this->apiVersion];
        }

        if (null !== $body = $request->getBody()) {
            // Pre-encoded by the handler (e.g. multipart advertising a MIME type).
            $httpOptions['body'] = $body;
        } elseif ('multipart/form-data' === $contentType) {
            // HttpClient builds the multipart body from an array and generates the
            // boundary, so the Content-Type header must not be sent explicitly.
            unset($httpOptions['headers']['Content-Type'], $httpOptions['headers']['content-type']);
            $httpOptions['body'] = $payload;
        } else {
            $httpOptions['headers']['Content-Type'] = 'application/json';
            $httpOptions['body'] = $this->encodeJsonBody($payload);
        }

        $response = $this->httpClient->request($request->getMethod(), $this->resolveUrl($path), $httpOptions);

        $this->throwOnHttpError($response);

        return new RawHttpResult($response);
    }

    private function resolveUrl(string $path): string
    {
        if (\in_array($path, self::V1_PATHS, true)) {
            return $this->baseUrl.'/openai'.$path;
        }

        // Classic surface: "/v1/audio/transcriptions" becomes
        // "/openai/deployments/{deployment}/audio/transcriptions".
        return \sprintf('%s/openai/deployments/%s/%s', $this->baseUrl, $this->deployment, ltrim(substr($path, \strlen('/v1')), '/'));
    }
}
