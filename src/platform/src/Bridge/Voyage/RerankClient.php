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

use Symfony\AI\Platform\Bridge\Voyage\Reranker\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Pauline Vos <pauline.vos@mongodb.com>
 */
final class RerankClient extends AbstractVoyageClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Reranker;
    }

    /**
     * @param array{query?: string, texts?: list<string>}|string $payload
     * @param array{top_k?: int, truncation?: bool}              $options
     */
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

        if (isset($options['top_k'])) {
            $body['top_k'] = $options['top_k'];
        }

        if (isset($options['truncation'])) {
            $body['truncation'] = $options['truncation'];
        }

        return $this->post('/v1/rerank', $body);
    }

    public function convert(RawResultInterface $result, array $options = []): RerankingResult
    {
        $this->throwOnError($result);

        $httpResponse = $result->getObject();

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $httpResponse->getContent(false)));
        }

        $data = $result->getData();

        if (!isset($data['data'])) {
            throw new RuntimeException('Response does not contain reranking results.');
        }

        return new RerankingResult(
            array_map(
                static fn (array $item): RerankingEntry => new RerankingEntry((int) $item['index'], (float) $item['relevance_score']),
                $data['data'],
            ),
        );
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }
}
