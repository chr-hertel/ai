<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cartesia;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
abstract class AbstractCartesiaClient implements ApiClientInterface
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

    /**
     * @param array<string, mixed> $payload
     */
    protected function post(string $path, array $payload, bool $multipart = false): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.$path, [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Cartesia-Version' => $this->version,
            ],
            $multipart ? 'body' : 'json' => $payload,
        ]));
    }
}
