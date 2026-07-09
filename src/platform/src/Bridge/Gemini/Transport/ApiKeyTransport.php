<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Transport;

use Symfony\AI\Platform\Exception\ExceedContextSizeException;
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
 * Google AI Studio (`generativelanguage.googleapis.com`) transport.
 *
 * Sends the API key via the `x-goog-api-key` header (the documented method
 * for direct Gemini API access). HTTP-level errors (401/400/429) become
 * typed exceptions; the handler stays free of protocol concerns.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ApiKeyTransport implements TransportInterface
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    private readonly string $baseUrl;

    private readonly EventSourceHttpClient $httpClient;

    /**
     * @param string $baseUrl Base URL of a Gemini-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://generativelanguage.googleapis.com',
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $headers = array_merge(
            ['x-goog-api-key' => $this->apiKey],
            $request->getHeaders(),
        );

        $headers['Content-Type'] = 'application/json';

        $response = $this->httpClient->request($request->getMethod(), $this->baseUrl.'/v1beta/'.ltrim($request->getPath(), '/'), [
            'headers' => $headers,
            'body' => $this->encodeJsonBody($request->getPayload()),
        ]);

        if (400 === $response->getStatusCode()) {
            $message = $this->extractErrorMessage($response) ?? '';

            if (str_contains($message, 'maximum number of tokens') || str_contains($message, 'input token count')) {
                throw new ExceedContextSizeException($message);
            }
        }

        $this->throwOnHttpError($response);

        return new RawHttpResult($response);
    }
}
