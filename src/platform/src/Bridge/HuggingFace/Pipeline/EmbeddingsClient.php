<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Pipeline;

use Symfony\AI\Platform\Bridge\HuggingFace\Embeddings;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbeddingsClient extends AbstractPipelineClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function convert(RawResultInterface $result, array $options = []): VectorResult
    {
        $data = $this->getResponseData($result);

        if (!isset($data[0]) || !\is_array($data[0])) {
            return new VectorResult([new Vector($data)]);
        }

        return new VectorResult(array_map(static fn (array $vector): Vector => new Vector($vector), $data));
    }

    protected function getPath(Model $model): string
    {
        // Sentence similarity models only return embeddings on the feature extraction pipeline route
        return parent::getPath($model).'/pipeline/feature-extraction';
    }
}
