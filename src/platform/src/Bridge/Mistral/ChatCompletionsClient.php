<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral;

use Symfony\AI\Platform\Bridge\Generic\ChatCompletionsClient as GenericChatCompletionsClient;
use Symfony\AI\Platform\Bridge\Generic\Completions\FinishReasonMapper;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;

/**
 * Mistral speaks the OpenAI chat-completions shape except for reasoning models,
 * which return `content` as a list of typed chunks (`thinking`, `text`) instead
 * of a plain string. Everything else is inherited.
 *
 * @author Benoit Bauchet <benoitbauchet@gmail.com>
 */
final class ChatCompletionsClient extends GenericChatCompletionsClient
{
    /**
     * @param array<string, mixed> $delta
     *
     * @return \Generator<int, ThinkingDelta|ThinkingComplete|TextDelta, mixed, string>
     */
    protected function yieldContentDeltas(array $delta, string $reasoning): \Generator
    {
        $content = $delta['content'] ?? null;

        if (!\is_array($content)) {
            return yield from parent::yieldContentDeltas($delta, $reasoning);
        }

        foreach ($content as $chunk) {
            $type = \is_array($chunk) ? ($chunk['type'] ?? null) : null;

            if ('thinking' === $type) {
                $thinking = $this->flattenThinking($chunk['thinking'] ?? []);
                if ('' !== $thinking) {
                    $reasoning .= $thinking;
                    yield new ThinkingDelta($thinking);
                }

                continue;
            }

            if ('text' === $type) {
                if ('' !== $reasoning) {
                    yield new ThinkingComplete($reasoning);
                    $reasoning = '';
                }

                $text = \is_string($chunk['text'] ?? null) ? $chunk['text'] : '';
                if ('' !== $text) {
                    yield new TextDelta($text);
                }
            }
        }

        return $reasoning;
    }

    /**
     * @param array<string, mixed> $choice
     */
    protected function convertChoice(array $choice): ResultInterface
    {
        $content = $choice['message']['content'] ?? null;

        if (!\is_array($content) || 'tool_calls' === ($choice['finish_reason'] ?? null)) {
            return parent::convertChoice($choice);
        }

        $results = [];
        foreach ($content as $chunk) {
            $type = \is_array($chunk) ? ($chunk['type'] ?? null) : null;

            if ('thinking' === $type) {
                $results[] = new ThinkingResult($this->flattenThinking($chunk['thinking'] ?? []));
            } elseif ('text' === $type && \is_string($chunk['text'] ?? null)) {
                $results[] = new TextResult($chunk['text']);
            }
        }

        if ([] === $results) {
            $results[] = new TextResult('');
        }

        return $this->withFinishReason(
            1 === \count($results) ? $results[0] : new MultiPartResult($results),
            FinishReasonMapper::map($choice['finish_reason'] ?? null),
        );
    }

    /**
     * @param list<array<string, mixed>> $chunks
     */
    private function flattenThinking(array $chunks): string
    {
        $thinking = '';
        foreach ($chunks as $chunk) {
            if ('text' === ($chunk['type'] ?? null) && \is_string($chunk['text'] ?? null)) {
                $thinking .= $chunk['text'];
            }
        }

        return $thinking;
    }
}
