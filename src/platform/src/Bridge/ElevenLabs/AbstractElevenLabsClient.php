<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
abstract class AbstractElevenLabsClient implements ApiClientInterface
{
    public function __construct(
        protected readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function post(string $path, array $payload, bool $multipart = false): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $path, [
            $multipart ? 'body' : 'json' => $payload,
        ]));
    }

    protected function throwOnUnsuccessfulResponse(RawResultInterface $result): void
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        if (200 !== $response->getStatusCode()) {
            $errorMessage = $this->extractErrorMessage($response)
                ?? \sprintf('The ElevenLabs API returned a non-successful status code "%d".', $response->getStatusCode());

            throw new RuntimeException($errorMessage);
        }
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);

            return $data['detail']['message'] ?? null;
        } catch (JsonException) {
            return null;
        }
    }
}
