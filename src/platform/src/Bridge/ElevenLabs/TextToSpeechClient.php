<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextToSpeechClient extends AbstractElevenLabsClient
{
    public function supports(Model $model): bool
    {
        return $model->supports(Capability::TEXT_TO_SPEECH);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $options = [...$model->getOptions(), ...$options];

        if (!\array_key_exists('voice', $options)) {
            throw new InvalidArgumentException('The voice option is required.');
        }

        $text = \is_string($payload) ? $payload : ($payload['text'] ?? throw new InvalidArgumentException('The payload must contain a "text" key.'));

        $voice = $model->getOptions()['voice'] ?? $options['voice'] ?? throw new InvalidArgumentException('The voice option is required.');
        $stream = $options['stream'] ?? false;

        $url = $stream
            ? \sprintf('text-to-speech/%s/stream', $voice)
            : \sprintf('text-to-speech/%s', $voice);

        unset($options['voice'], $options['stream']);

        return $this->post($url, [
            'text' => $text,
            'model_id' => $model->getName(),
            ...$options,
        ]);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->throwOnUnsuccessfulResponse($result);

        /** @var ResponseInterface $response */
        $response = $result->getObject();

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertToGenerator($response));
        }

        return new BinaryResult($response->getContent(), 'audio/mpeg');
    }

    public function getTokenUsageExtractor(): null
    {
        return null;
    }

    private function convertToGenerator(ResponseInterface $response): \Generator
    {
        foreach ($this->httpClient->stream($response) as $chunk) {
            if ($chunk->isFirst() || $chunk->isLast()) {
                continue;
            }

            if ('' === $chunk->getContent()) {
                continue;
            }

            yield new BinaryDelta($chunk->getContent());
        }
    }
}
