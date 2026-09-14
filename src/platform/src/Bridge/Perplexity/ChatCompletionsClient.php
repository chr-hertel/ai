<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity;

use Symfony\AI\Platform\Bridge\Generic\ChatCompletionsClient as GenericChatCompletionsClient;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChatCompletionsClient extends GenericChatCompletionsClient
{
    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        $result = parent::convert($raw, $options);

        if ($options['stream'] ?? false) {
            return $result;
        }

        $data = $raw->getData();

        $metadata = $result->getMetadata();

        if (\array_key_exists('search_results', $data)) {
            $metadata->add('search_results', $data['search_results']);
        }

        if (\array_key_exists('citations', $data)) {
            $metadata->add('citations', $data['citations']);
        }

        return $result;
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    protected function yieldChunkMetadata(array $data): \Generator
    {
        if (isset($data['search_results'])) {
            yield new MetadataDelta('search_results', $data['search_results']);
        }

        if (isset($data['citations'])) {
            yield new MetadataDelta('citations', $data['citations']);
        }
    }

    protected function requiresStreamFinishReason(): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $choice
     */
    protected function convertChoice(array $choice): ResultInterface
    {
        if (!\in_array($choice['finish_reason'], ['stop', 'length'], true)) {
            throw new RuntimeException(\sprintf('Unsupported finish reason "%s".', $choice['finish_reason']));
        }

        return $this->withFinishReason(new TextResult($choice['message']['content']), FinishReasonMapper::map($choice['finish_reason']));
    }
}
