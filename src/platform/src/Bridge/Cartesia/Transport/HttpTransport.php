<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cartesia\Transport;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cartesia HTTP transport (api.cartesia.ai, Bearer auth + Cartesia-Version header).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport implements TransportInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Cartesia-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $version,
        string $baseUrl = 'https://api.cartesia.ai',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $headers = array_merge(['Cartesia-Version' => $this->version], $request->getHeaders());
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? 'application/json';

        $httpOptions = [
            'auth_bearer' => $this->apiKey,
            'headers' => $headers,
        ];

        if ('multipart/form-data' === $contentType) {
            unset($httpOptions['headers']['Content-Type'], $httpOptions['headers']['content-type']);
            $httpOptions['body'] = $request->getPayload();
        } else {
            $httpOptions['json'] = $request->getPayload();
        }

        return new RawHttpResult($this->httpClient->request($request->getMethod(), $this->baseUrl.$request->getPath(), $httpOptions));
    }
}
