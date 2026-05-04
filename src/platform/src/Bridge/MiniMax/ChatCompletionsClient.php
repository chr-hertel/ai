<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax;

use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;

/**
 * MiniMax `/chat/completions` client.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChatCompletionsClient extends AbstractMiniMaxClient
{
    public const ENDPOINT = 'minimax.chat_completions';

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!\is_array($payload)) {
            throw new InvalidArgumentException(\sprintf('The payload is not an array, given "%s".', get_debug_type($payload)));
        }

        return $this->post('chat/completions', [
            ...$options,
            'model' => $model->getName(),
            'messages' => $payload['messages'],
        ]);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->guardHttpStatus($result);

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        return new TextResult($result->getData()['choices'][0]['message']['content']);
    }

    /**
     * @return \Generator<int, TextDelta>
     */
    private function convertStream(RawResultInterface $result): \Generator
    {
        $sawChunk = false;
        $sawFinishReason = false;

        foreach ($result->getDataStream() as $chunk) {
            if (!\is_array($chunk)) {
                continue;
            }

            $sawChunk = true;

            if (null !== ($chunk['choices'][0]['finish_reason'] ?? null)) {
                $sawFinishReason = true;
            }

            $content = $chunk['choices'][0]['delta']['content'] ?? '';

            if ('' === $content) {
                continue;
            }

            yield new TextDelta($content);
        }

        if ($sawChunk && !$sawFinishReason) {
            throw new IncompleteStreamException('The MiniMax stream ended before a finish reason.');
        }
    }
}
