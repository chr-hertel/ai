<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decart;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
abstract class AbstractDecartClient implements ApiClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Decart-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.decart.ai/v1',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function post(string $path, array $payload): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.$path, [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'multipart/form-data',
            ],
            'body' => $payload,
        ]));
    }
}
