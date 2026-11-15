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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RerankingResult;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class RerankClient extends AbstractTogetherClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Together && $model->supports(Capability::RERANKING);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!\is_array($payload) || !isset($payload['query'])) {
            throw new InvalidArgumentException('The reranking payload must contain a "query" key.');
        }

        // Together expects "documents"; "texts" is accepted as an alias for consistency with other rerank bridges.
        $documents = $payload['documents'] ?? $payload['texts'] ?? null;
        if (null === $documents) {
            throw new InvalidArgumentException('The reranking payload must contain a "documents" (or "texts") key.');
        }

        return $this->postJson('/v1/rerank', array_merge($options, [
            'model' => $model->getName(),
            'query' => $payload['query'],
            'documents' => $documents,
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): RerankingResult
    {
        $this->throwOnError($result);

        $data = $result->getData();

        $this->throwOnApiError($data);

        if (!isset($data['results']) || !\is_array($data['results'])) {
            throw new RuntimeException('Response does not contain reranking results.');
        }

        $entries = [];

        foreach ($data['results'] as $item) {
            if (!\is_array($item) || !isset($item['index'], $item['relevance_score'])) {
                continue;
            }

            if (!is_numeric($item['index']) || !is_numeric($item['relevance_score'])) {
                continue;
            }

            $entries[] = new RerankingEntry((int) $item['index'], (float) $item['relevance_score']);
        }

        return new RerankingResult($entries);
    }
}
