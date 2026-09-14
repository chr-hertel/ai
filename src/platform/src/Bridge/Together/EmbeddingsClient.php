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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class EmbeddingsClient extends AbstractTogetherClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Together && $model->supports(Capability::EMBEDDINGS);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return $this->postJson('/v1/embeddings', array_merge($options, [
            'model' => $model->getName(),
            'input' => $payload,
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): VectorResult
    {
        $this->throwOnError($result);

        $data = $result->getData();

        $this->throwOnApiError($data);

        if (!isset($data['data']) || !\is_array($data['data']) || [] === $data['data']) {
            throw new RuntimeException('Response does not contain embeddings.');
        }

        $vectors = [];

        foreach ($data['data'] as $item) {
            if (!\is_array($item) || !isset($item['embedding']) || !\is_array($item['embedding'])) {
                throw new RuntimeException('Response does not contain a valid "embedding" key.');
            }

            /** @var list<float> $embedding */
            $embedding = $item['embedding'];

            $vectors[] = new Vector($embedding);
        }

        return new VectorResult($vectors);
    }
}
