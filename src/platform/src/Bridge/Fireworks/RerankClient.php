<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RerankClient implements ApiClientInterface
{
    public function __construct(
        private readonly HttpTransport $transport,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Fireworks && $model->supports(Capability::RERANKING);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!\is_array($payload) || !isset($payload['query'], $payload['documents'])) {
            throw new InvalidArgumentException('Rerank payload must be an array with "query" and "documents" keys.');
        }

        return $this->transport->send('/v1/rerank', array_merge($options, [
            'model' => $model->getName(),
            'query' => $payload['query'],
            'documents' => $payload['documents'],
        ]));
    }

    public function convert(RawResultInterface $raw, array $options = []): RerankingResult
    {
        $this->transport->throwOnError($raw, $options);

        $data = $raw->getData();

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

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
