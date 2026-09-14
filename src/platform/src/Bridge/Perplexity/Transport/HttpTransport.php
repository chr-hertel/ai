<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity\Transport;

use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport as GenericHttpTransport;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport extends GenericHttpTransport
{
    /**
     * @param array<string, string> $extraHeaders
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
        #[\SensitiveParameter] string $apiKey,
        array $extraHeaders = [],
    ) {
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }

        if (!str_starts_with($apiKey, 'pplx-')) {
            throw new InvalidArgumentException('The API key must start with "pplx-".');
        }

        parent::__construct($httpClient, $baseUrl, $apiKey, $extraHeaders);
    }

    protected function throwOnContextOverflow(ResponseInterface $response): void
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return;
        }

        $error = $data['error'] ?? [];
        $message = $error['message'] ?? '';

        if ('too_many_prompt_tokens' === ($error['type'] ?? null) || str_contains(strtolower($message), 'too long')) {
            throw new ExceedContextSizeException('' !== $message ? $message : 'Context size exceeded');
        }

        parent::throwOnContextOverflow($response);
    }
}
