<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractVoyageClient implements ApiClientInterface
{
    use HttpStatusErrorHandlingTrait;

    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Voyage-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.voyageai.com',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function post(string $path, array $body): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.$path, [
            'auth_bearer' => $this->apiKey,
            'json' => $body,
        ]));
    }

    protected function throwOnError(RawResultInterface $result): void
    {
        if ($result instanceof RawHttpResult) {
            $this->throwOnHttpError($result->getObject());
        }
    }

    /**
     * Overrides the shared lookup: Voyage reports errors as `{"detail": "..."}`.
     */
    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $detail = $response->toArray(false)['detail'] ?? null;
        } catch (DecodingExceptionInterface) {
            return null;
        }

        return \is_string($detail) ? $detail : null;
    }
}
