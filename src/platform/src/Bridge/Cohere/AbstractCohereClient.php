<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractCohereClient implements ApiClientInterface
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Cohere-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.cohere.com',
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
                'Content-Type' => $multipart ? 'multipart/form-data' : 'application/json',
            ],
            'body' => $multipart ? $payload : $this->encodeJsonBody($payload),
        ]));
    }

    protected function throwOnError(RawResultInterface $result): void
    {
        if (!$result instanceof RawHttpResult) {
            return;
        }

        $httpResponse = $result->getObject();

        if (400 === $httpResponse->getStatusCode()) {
            $message = $this->extractErrorMessage($httpResponse) ?? '';

            if (str_contains(strtolower($message), 'too many tokens')) {
                throw new ExceedContextSizeException($message);
            }
        }

        $this->throwOnHttpError($httpResponse);

        if (200 !== $code = $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $code, $httpResponse->getContent(false)));
        }
    }
}
