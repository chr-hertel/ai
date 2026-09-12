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
use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TransportInterface;

/**
 * ElevenLabs speech-to-text contract handler.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SpeechToTextClient implements EndpointClientInterface
{
    public const ENDPOINT = 'elevenlabs.speech_to_text';

    public function __construct(
        private readonly TransportInterface $transport,
    ) {
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supports(Capability::SPEECH_TO_TEXT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!\is_array($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array for speech-to-text request, got "%s".', \gettype($payload)));
        }

        if (!\array_key_exists('input_audio', $payload) || !\is_array($payload['input_audio'])) {
            throw new InvalidArgumentException('Input audio (with a "path" key) is required for speech-to-text request.');
        }

        $body = [
            'file' => fopen($payload['input_audio']['path'], 'r'),
            'model_id' => $model->getName(),
        ];

        foreach ($options as $key => $value) {
            if (null === $value) {
                continue;
            }

            $body[$key] = self::stringifyOption($key, $value);
        }

        $envelope = new RequestEnvelope(
            payload: $body,
            headers: ['Content-Type' => 'multipart/form-data'],
            path: 'speech-to-text',
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): TextResult
    {
        return new TextResult($raw->getData()['text']);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    /**
     * The multipart body carries every option as a string field.
     */
    private static function stringifyOption(int|string $key, mixed $value): string
    {
        if (\is_array($value)) {
            try {
                return json_encode($value, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new InvalidArgumentException(\sprintf('The speech-to-text option "%s" could not be JSON-encoded.', $key), 0, $e);
            }
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_string($value)) {
            return $value;
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException(\sprintf('The speech-to-text option "%s" must be a scalar or an array, got "%s".', $key, get_debug_type($value)));
    }
}
