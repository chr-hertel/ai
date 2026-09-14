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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbedClient extends AbstractOllamaClient
{
    private const EMBED_TOP_LEVEL_KEYS = [
        'truncate',
        'keep_alive',
        'dimensions',
    ];

    public function supports(Model $model): bool
    {
        return $model->supports(Capability::EMBEDDINGS);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $options = self::normalizeOllamaOptions($options, self::EMBED_TOP_LEVEL_KEYS);

        return new RawHttpResult($this->httpClient->request('POST', '/api/embed', [
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $data = $result->getData();

        if (!isset($data['embeddings']) || [] === $data['embeddings']) {
            throw new RuntimeException('Response does not contain embeddings.');
        }

        return new VectorResult(
            array_map(
                static fn (array $embedding): Vector => new Vector($embedding),
                $data['embeddings'],
            ),
        );
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }
}
