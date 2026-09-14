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

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class EmbedClient extends AbstractCohereClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $texts = \is_array($payload) ? $payload : [$payload];

        $body = [
            'model' => $model->getName(),
            'texts' => $texts,
            'input_type' => ($options['input_type'] ?? $model->getOptions()['input_type'] ?? InputType::SearchDocument)->value,
        ];

        if (isset($options['embedding_types'])) {
            $body['embedding_types'] = $options['embedding_types'];
        }

        return $this->post('/v2/embed', $body);
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): VectorResult
    {
        $this->throwOnError($result);

        $data = $result->getData();

        if (!isset($data['embeddings']['float'])) {
            throw new RuntimeException('Response does not contain embedding data.');
        }

        return new VectorResult(
            array_map(
                static fn (array $embedding): Vector => new Vector($embedding),
                $data['embeddings']['float'],
            ),
        );
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new MetaBilledUnitsTokenUsageExtractor();
    }
}
