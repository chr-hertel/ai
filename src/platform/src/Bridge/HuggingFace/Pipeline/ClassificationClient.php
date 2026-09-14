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

use Symfony\AI\Platform\Bridge\HuggingFace\Classification;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\Classification as ClassificationEntry;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ClassificationResult;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ClassificationClient extends AbstractPipelineClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Classification;
    }

    public function convert(RawResultInterface $result, array $options = []): ObjectResult
    {
        $data = $this->getResponseData($result);

        // Text classification nests the labels in a list per input
        /** @var array<array{label: string, score: float}> $classifications */
        $classifications = isset($data[0][0]) ? $data[0] : $data;

        return new ObjectResult(new ClassificationResult(
            array_map(static fn (array $item) => new ClassificationEntry($item['label'], $item['score']), $classifications)
        ));
    }
}
