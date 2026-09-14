<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

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
abstract class AbstractOllamaClient implements ApiClientInterface
{
    use HttpStatusErrorHandlingTrait;

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string>        $topLevelKeys
     *
     * @return array<string, mixed>
     */
    protected static function normalizeOllamaOptions(array $options, array $topLevelKeys): array
    {
        $topLevelOptions = [];
        $nested = [];

        if (isset($options['options']) && \is_array($options['options'])) {
            $nested = $options['options'];
        }

        foreach ($options as $key => $value) {
            if ('options' === $key) {
                continue;
            }

            if (\in_array($key, $topLevelKeys, true)) {
                $topLevelOptions[$key] = $value;
            } else {
                $nested[$key] ??= $value;
            }
        }

        if ([] !== $nested) {
            $topLevelOptions['options'] = $nested;
        }

        return $topLevelOptions;
    }

    protected function throwOnError(RawResultInterface $result): void
    {
        if ($result instanceof RawHttpResult) {
            $this->throwOnHttpError($result->getObject());
        }
    }

    /**
     * Overrides the shared lookup: Ollama reports errors as `{"error": "..."}`.
     */
    protected function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $error = $response->toArray(false)['error'] ?? null;
        } catch (DecodingExceptionInterface) {
            return null;
        }

        return \is_string($error) ? $error : null;
    }
}
