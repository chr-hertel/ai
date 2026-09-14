<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Rerank;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\OpenRouter\RerankModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class Client implements ApiClientInterface
{
    private readonly string $baseUrl;
    private readonly ResultConverter $resultConverter;

    /**
     * @param string $baseUrl Base URL of an OpenRouter-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://openrouter.ai/api',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->resultConverter = new ResultConverter();
    }

    public function supports(Model $model): bool
    {
        return $model instanceof RerankModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!\is_array($payload) || !isset($payload['query'], $payload['texts'])) {
            throw new InvalidArgumentException('Reranker payload must be an array with "query" and "texts" keys.');
        }

        $body = [
            'model' => $model->getName(),
            'query' => $payload['query'],
            'documents' => $payload['texts'],
        ];

        if (isset($options['top_n'])) {
            $body['top_n'] = $options['top_n'];
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/rerank', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]));
    }

    public function convert(RawResultInterface $raw, array $options = []): RerankingResult
    {
        return $this->resultConverter->convert($raw, $options);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return $this->resultConverter->getTokenUsageExtractor();
    }
}
