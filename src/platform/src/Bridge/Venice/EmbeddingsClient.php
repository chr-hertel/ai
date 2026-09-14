<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class EmbeddingsClient extends AbstractVeniceClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Venice && $model->supports(Capability::EMBEDDINGS);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return $this->postJson('embeddings', [
            'encoding_format' => 'float',
            ...$options,
            'input' => (new VenicePayload($payload))->asEmbeddingsPayload(),
            'model' => $model->getName(),
        ]);
    }

    public function convert(RawResultInterface $result, array $options = []): VectorResult
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        $payload = $response->toArray();
        $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if ([] === $data) {
            throw new InvalidArgumentException('No embeddings found in the response.');
        }

        return new VectorResult(array_map(
            static function (mixed $entry): VectorInterface {
                if (!\is_array($entry) || !\is_array($entry['embedding'] ?? null)) {
                    throw new InvalidArgumentException('Expected embedding to be an array.');
                }

                return new Vector(array_map(
                    static function (mixed $v): float {
                        if (!\is_float($v) && !\is_int($v)) {
                            throw new InvalidArgumentException('Expected embedding value to be a number.');
                        }

                        return (float) $v;
                    },
                    array_values($entry['embedding']),
                ));
            },
            $data,
        ));
    }
}
