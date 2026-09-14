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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MultimodalEmbeddingsClient extends AbstractVoyageClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Voyage && $model->supports(Capability::INPUT_MULTIMODAL);
    }

    /**
     * @param array{input_type?: 'query'|'document'|null, truncation?: bool, encoding?: string} $options
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return $this->post('/v1/multimodalembeddings', [
            'model' => $model->getName(),
            'inputs' => $payload,
            'input_type' => $options['input_type'] ?? null,
            'truncation' => $options['truncation'] ?? true,
            'output_encoding' => $options['encoding'] ?? null,
        ]);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->throwOnError($result);

        $result = $result->getData();

        if (!isset($result['data'])) {
            throw new RuntimeException('Response does not contain embedding data.');
        }

        return new VectorResult(
            array_map(
                static fn (array $data) => new Vector($data['embedding']),
                $result['data'],
            ),
        );
    }

    public function getTokenUsageExtractor(): null
    {
        return null;
    }
}
