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
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ChatCompletionsClient extends AbstractVeniceClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Venice && $model->supports(Capability::INPUT_MESSAGES);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if ($options['stream'] ?? false) {
            $streamOptions = (\array_key_exists('stream_options', $options) && \is_array($options['stream_options'])) ? $options['stream_options'] : [];

            if (!isset($streamOptions['include_usage'])) {
                $streamOptions['include_usage'] = true;
            }

            $options['stream_options'] = $streamOptions;
        }

        if (isset($options['venice_parameters']) && $options['venice_parameters'] instanceof VeniceParameters) {
            $options['venice_parameters'] = $options['venice_parameters']->toArray();
        }

        return $this->postJson('chat/completions', [
            ...$options,
            'messages' => (new VenicePayload($payload))->asCompletionPayload(),
            'model' => $model->getName(),
        ]);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertCompletionToGenerator($result));
        }

        /** @var ResponseInterface $response */
        $response = $result->getObject();

        $payload = $response->toArray();
        $choices = \is_array($payload['choices'] ?? null) ? $payload['choices'] : [];
        $firstChoice = \is_array($choices[0] ?? null) ? $choices[0] : [];
        $message = \is_array($firstChoice['message'] ?? null) ? $firstChoice['message'] : [];

        if (\is_array($message['tool_calls'] ?? null) && [] !== $message['tool_calls']) {
            /** @var list<array<string, mixed>> $toolCalls */
            $toolCalls = array_values($message['tool_calls']);

            return $this->convertToolCalls($toolCalls);
        }

        if (!\is_string($message['content'] ?? null) || '' === $message['content']) {
            throw new InvalidArgumentException('No completions found in the response.');
        }

        return new TextResult($message['content']);
    }

    /**
     * @param list<array<string, mixed>> $toolCalls
     */
    private function convertToolCalls(array $toolCalls): ToolCallResult
    {
        $calls = [];

        foreach ($toolCalls as $toolCall) {
            $id = \is_string($toolCall['id'] ?? null) ? $toolCall['id'] : '';
            $function = \is_array($toolCall['function'] ?? null) ? $toolCall['function'] : [];
            $name = \is_string($function['name'] ?? null) ? $function['name'] : '';
            $arguments = $function['arguments'] ?? '{}';

            if (\is_string($arguments)) {
                $decoded = json_decode($arguments, true);
                $arguments = \is_array($decoded) ? $decoded : [];
            }

            if (!\is_array($arguments)) {
                $arguments = [];
            }

            /** @var array<string, mixed> $typedArguments */
            $typedArguments = $arguments;

            $calls[] = new ToolCall($id, $name, $typedArguments);
        }

        return new ToolCallResult($calls);
    }

    private function convertCompletionToGenerator(RawResultInterface $result): \Generator
    {
        $accumulatedToolCalls = [];

        foreach ($result->getDataStream() as $chunk) {
            if (!\is_array($chunk)) {
                continue;
            }

            $choices = $chunk['choices'] ?? [];

            if (\is_array($choices) && [] !== $choices) {
                $firstChoice = $choices[0] ?? [];

                if (\is_array($firstChoice)) {
                    $delta = \is_array($firstChoice['delta'] ?? null) ? $firstChoice['delta'] : [];

                    $content = $delta['content'] ?? null;
                    if (\is_string($content) && '' !== $content) {
                        yield new TextDelta($content);
                    }

                    $reasoning = $delta['reasoning_content'] ?? $delta['reasoning'] ?? null;
                    if (\is_string($reasoning) && '' !== $reasoning) {
                        yield new ThinkingDelta($reasoning);
                    }

                    if (\is_array($delta['tool_calls'] ?? null)) {
                        foreach ($delta['tool_calls'] as $toolCall) {
                            if (!\is_array($toolCall)) {
                                continue;
                            }

                            $index = \is_int($toolCall['index'] ?? null) ? $toolCall['index'] : 0;

                            if (!isset($accumulatedToolCalls[$index])) {
                                $accumulatedToolCalls[$index] = ['id' => '', 'name' => '', 'arguments' => ''];
                            }

                            if (\is_string($toolCall['id'] ?? null)) {
                                $accumulatedToolCalls[$index]['id'] = $toolCall['id'];
                            }

                            $function = \is_array($toolCall['function'] ?? null) ? $toolCall['function'] : [];

                            if (\is_string($function['name'] ?? null)) {
                                $accumulatedToolCalls[$index]['name'] = $function['name'];
                            }

                            if (\is_string($function['arguments'] ?? null)) {
                                $accumulatedToolCalls[$index]['arguments'] .= $function['arguments'];
                            }
                        }
                    }
                }
            }

            $usage = $chunk['usage'] ?? null;

            if (\is_array($usage)) {
                $promptTokens = isset($usage['prompt_tokens']) && \is_int($usage['prompt_tokens']) ? $usage['prompt_tokens'] : null;
                $completionTokens = isset($usage['completion_tokens']) && \is_int($usage['completion_tokens']) ? $usage['completion_tokens'] : null;
                $totalTokens = isset($usage['total_tokens']) && \is_int($usage['total_tokens']) ? $usage['total_tokens'] : null;

                yield new TokenUsage(
                    promptTokens: $promptTokens,
                    completionTokens: $completionTokens,
                    totalTokens: $totalTokens,
                );
            }
        }

        if ([] !== $accumulatedToolCalls) {
            $calls = [];

            foreach ($accumulatedToolCalls as $partial) {
                $arguments = json_decode($partial['arguments'] ?: '{}', true);
                /** @var array<string, mixed> $arguments */
                $arguments = \is_array($arguments) ? $arguments : [];
                $calls[] = new ToolCall($partial['id'], $partial['name'], $arguments);
            }

            yield new ToolCallComplete($calls);
        }
    }
}
