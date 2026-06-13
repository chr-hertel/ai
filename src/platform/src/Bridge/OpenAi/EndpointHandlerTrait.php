<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Shared boilerplate for OpenAI endpoint handlers: token usage attachment and
 * the raw-result fallback, mirroring the behavior of DeferredResult::getResult().
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
trait EndpointHandlerTrait
{
    /**
     * @param array<string, mixed> $options
     */
    private function attachTokenUsage(ResultInterface $result, ?TokenUsageExtractorInterface $extractor, RawResultInterface $rawResult, array $options): void
    {
        if (null === $extractor) {
            return;
        }

        $tokenUsage = $extractor->extract($rawResult, $options);

        if (null !== $tokenUsage) {
            $result->getMetadata()->add('token_usage', $tokenUsage);
        }
    }

    private function finalizeResult(ResultInterface $result, RawResultInterface $rawResult): ResultInterface
    {
        if (null === $result->getRawResult()) {
            $result->setRawResult($rawResult);
        }

        return $result;
    }
}
