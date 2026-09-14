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

use Symfony\AI\Platform\Bridge\Cohere\Llm\FinishReasonMapper;
use Symfony\AI\Platform\Bridge\Cohere\Llm\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\MalformedToolCallException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\FinishReason\FinishReasonAwareTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ChatClient extends AbstractCohereClient
{
    use FinishReasonAwareTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Cohere;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        return $this->post('/v2/chat', array_merge($options, $payload));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $this->throwOnError($result);

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        $finishReason = $data['finish_reason'] ?? null;

        if ('COMPLETE' === $finishReason) {
            $text = $data['message']['content'][0]['text'] ?? '';

            return $this->withFinishReason(new TextResult($text), FinishReasonMapper::map($finishReason));
        }

        if ('TOOL_CALL' === $finishReason) {
            return $this->withFinishReason(new ToolCallResult(array_map($this->convertToolCall(...), $data['message']['tool_calls'] ?? [])), FinishReasonMapper::map($finishReason));
        }

        throw new RuntimeException(\sprintf('Unsupported finish reason "%s".', $finishReason));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $streamFinishReason = null;
        $toolCalls = [];
        $sawChunk = false;
        $sawMessageEnd = false;
        foreach ($result->getDataStream() as $data) {
            $sawChunk = true;
            $type = $data['type'] ?? null;

            if ('content-delta' === $type) {
                yield new TextDelta($data['delta']['message']['content']['text'] ?? '');
                continue;
            }

            if ('tool-call-start' === $type) {
                $toolCall = $data['delta']['message']['tool_calls'] ?? null;
                if (null !== $toolCall) {
                    $id = $toolCall['id'] ?? '';
                    $name = $toolCall['function']['name'] ?? '';
                    $toolCalls[] = [
                        'id' => $id,
                        'function' => [
                            'name' => $name,
                            'arguments' => $toolCall['function']['arguments'] ?? '',
                        ],
                    ];

                    if ('' !== $id) {
                        yield new ToolCallStart($id, $name);
                    }
                }
                continue;
            }

            if ('tool-call-delta' === $type) {
                if ([] !== $toolCalls) {
                    $lastIndex = \count($toolCalls) - 1;
                    $partialJson = $data['delta']['message']['tool_calls']['function']['arguments'] ?? '';
                    $toolCalls[$lastIndex]['function']['arguments'] .= $partialJson;

                    if ('' !== $partialJson && '' !== $toolCalls[$lastIndex]['id']) {
                        yield new ToolInputDelta($toolCalls[$lastIndex]['id'], $toolCalls[$lastIndex]['function']['name'], $partialJson);
                    }
                }
                continue;
            }

            if ('message-end' === $type) {
                $sawMessageEnd = true;

                $error = $data['delta']['error'] ?? null;
                $finishReason = $data['delta']['finish_reason'] ?? null;
                if (null !== $error || 'ERROR' === $finishReason) {
                    $message = \is_array($error) ? ($error['message'] ?? 'Unknown error') : ($error ?? 'Unknown error');

                    throw new RuntimeException(\sprintf('Cohere stream error: "%s".', $message));
                }

                if ([] !== $toolCalls) {
                    yield new ToolCallComplete(array_map($this->convertToolCall(...), $toolCalls));
                }

                if (null !== $finishReason) {
                    $streamFinishReason = FinishReasonMapper::map($finishReason);
                }
            }
        }

        if ($sawChunk && !$sawMessageEnd) {
            throw new IncompleteStreamException('Cohere stream ended before message-end.');
        }

        if (null !== $streamFinishReason) {
            yield new MetadataDelta('finish_reason', $streamFinishReason);
        }
    }

    /**
     * @param array{
     *     id: string,
     *     function: array{
     *         name: string,
     *         arguments: string
     *     }
     * } $toolCall
     *
     * @throws MalformedToolCallException
     */
    private function convertToolCall(array $toolCall): ToolCall
    {
        $argumentsJson = (string) $toolCall['function']['arguments'];
        $arguments = [];
        if ('' !== $argumentsJson) {
            try {
                $arguments = json_decode($argumentsJson, true, flags: \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new MalformedToolCallException(\sprintf('Cohere returned malformed JSON arguments for the "%s" tool: "%s"', $toolCall['function']['name'], $e->getMessage()), 0, $e);
            }
        }

        return new ToolCall($toolCall['id'], $toolCall['function']['name'], $arguments);
    }
}
