<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Transport;

use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Generic Bearer-auth HTTP transport for OpenAI-compatible providers.
 *
 * Per-bridge wiring just constructs one of these with the right base URL
 * and API key; nothing else needs to change.
 *
 * Per-provider quirks (for example a non-Bearer auth scheme, custom
 * headers, query-string keys) are not in scope here — bridges that need
 * those still ship their own {@see TransportInterface} implementation.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport implements TransportInterface
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    private readonly EventSourceHttpClient $httpClient;

    /**
     * @param array<string, string> $extraHeaders Static per-deployment headers (e.g. provider-specific)
     */
    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        private readonly array $extraHeaders = [],
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->extraHeaders,
            $request->getHeaders(),
        );

        $httpOptions = [
            'headers' => $headers,
            'body' => $this->encodeJsonBody($request->getPayload()),
        ];

        if (null !== $this->apiKey) {
            $httpOptions['auth_bearer'] = $this->apiKey;
        }

        $response = $this->httpClient->request($request->getMethod(), rtrim($this->baseUrl, '/').$request->getPath(), $httpOptions);

        if (400 === $response->getStatusCode()) {
            $this->throwOnContextOverflow($response);
        }

        $this->throwOnHttpError($response);

        return new RawHttpResult($response);
    }

    /**
     * OpenAI-compatible providers report a context overflow as a 400; the marker is
     * either an `context_length_exceeded` error code or a message mentioning it.
     */
    private function throwOnContextOverflow(ResponseInterface $response): void
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return;
        }

        $code = $data['error']['code'] ?? $data['code'] ?? null;
        $message = $data['error']['message'] ?? $data['message'] ?? '';

        if ('context_length_exceeded' === $code || str_contains($message, 'context length')) {
            throw new ExceedContextSizeException('' !== $message ? $message : 'Context size exceeded');
        }
    }
}
