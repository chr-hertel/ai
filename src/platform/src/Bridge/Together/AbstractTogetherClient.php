<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Together;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\InvalidRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Base for the Together API clients: the HTTP client they share is scoped to the Together endpoint
 * and carries the API key, so a client only names its path and its body.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractTogetherClient implements ApiClientInterface
{
    use HttpStatusErrorHandlingTrait;

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    /**
     * @param array<string|int, mixed> $payload
     */
    protected function postJson(string $path, array $payload): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $path, ['json' => $payload]));
    }

    protected function throwOnError(RawResultInterface $result): void
    {
        if ($result instanceof RawHttpResult) {
            $this->throwOnHttpError($result->getObject());
        }
    }

    /**
     * Together reports a rejected request through an "error" key of an otherwise successful response.
     *
     * @param array<string, mixed> $data
     */
    protected function throwOnApiError(array $data): void
    {
        if (!isset($data['error'])) {
            return;
        }

        $error = $data['error'];
        $message = 'Unknown error';
        $code = null;

        if (\is_array($error)) {
            if (isset($error['message']) && \is_string($error['message'])) {
                $message = $error['message'];
            }

            if (isset($error['code']) && \is_string($error['code'])) {
                $code = $error['code'];
            } elseif (isset($error['type']) && \is_string($error['type'])) {
                $code = $error['type'];
            }
        }

        if ('content_filter' === $code) {
            throw new ContentFilterException($message);
        }

        if ('invalid_request_error' === $code) {
            throw new InvalidRequestException($message);
        }

        throw new RuntimeException($message);
    }
}
