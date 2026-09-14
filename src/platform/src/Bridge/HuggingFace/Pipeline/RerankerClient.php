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

use Symfony\AI\Platform\Bridge\HuggingFace\Reranker;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RerankingResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RerankerClient extends AbstractPipelineClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Reranker;
    }

    public function convert(RawResultInterface $result, array $options = []): RerankingResult
    {
        return new RerankingResult(array_map(
            static fn (array $entry): RerankingEntry => new RerankingEntry((int) $entry['index'], (float) $entry['score']),
            $this->getResponseData($result),
        ));
    }
}
