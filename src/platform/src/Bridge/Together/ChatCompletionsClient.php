<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Together;

use Symfony\AI\Platform\Bridge\Generic\Completions\CompletionsConversionTrait;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ChatCompletionsClient extends AbstractTogetherClient
{
    use CompletionsConversionTrait {
        CompletionsConversionTrait::convertChoice as private doConvertChoice;
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Together && $model->supports(Capability::INPUT_MESSAGES);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        return $this->postJson('/v1/chat/completions', array_merge($options, $payload));
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->throwOnError($result);

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        $this->throwOnApiError($data);

        if (!isset($data['choices']) || !\is_array($data['choices']) || [] === $data['choices']) {
            throw new RuntimeException('Result does not contain choices.');
        }

        /** @var list<array{index: int, message: array{role: 'assistant', content: ?string, tool_calls: list<array{id: string, type: 'function', function: array{name: string, arguments: string}}>, refusal: ?mixed}, logprobs: string, finish_reason: 'stop'|'eos'|'length'|'tool_calls'|'content_filter'}> $rawChoices */
        $rawChoices = $data['choices'];

        $choices = array_map($this->convertChoice(...), $rawChoices);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult($choices);
    }

    /**
     * Together uses the additional "eos" finish reason for completions that naturally
     * reached an end-of-sequence token, which is equivalent to "stop".
     *
     * @param array{index: int, message: array{role: 'assistant', content: ?string, tool_calls: list<array{id: string, type: 'function', function: array{name: string, arguments: string}}>, refusal: ?mixed}, logprobs: string, finish_reason: 'stop'|'eos'|'length'|'tool_calls'|'content_filter'} $choice
     */
    private function convertChoice(array $choice): ResultInterface
    {
        if ('eos' === $choice['finish_reason']) {
            $choice['finish_reason'] = 'stop';
        }

        return $this->doConvertChoice($choice);
    }
}
