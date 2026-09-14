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

use Symfony\AI\Platform\Bridge\ElevenLabs\Result\AdditionalFormat;
use Symfony\AI\Platform\Bridge\ElevenLabs\Result\Transcript;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechToTextClient extends AbstractElevenLabsClient
{
    public function supports(Model $model): bool
    {
        return $model->supports(Capability::SPEECH_TO_TEXT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!\is_array($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array for speech-to-text request, got "%s".', \gettype($payload)));
        }

        if (!\array_key_exists('input_audio', $payload)) {
            throw new InvalidArgumentException('Input audio is required for speech-to-text request.');
        }

        if (!\is_array($payload['input_audio'])) {
            throw new InvalidArgumentException('Input audio must be an array with a "path" key for speech-to-text request.');
        }

        $body = [
            'file' => fopen($payload['input_audio']['path'], 'r'),
            'model_id' => $model->getName(),
        ];

        foreach ([...$model->getOptions(), ...$options] as $key => $value) {
            if (null === $value) {
                continue;
            }

            $body[$key] = $this->stringifySpeechToTextOption($key, $value);
        }

        return $this->post('speech-to-text', $body, true);
    }

    /**
     * The speech-to-text response always carries the plain transcript text and, when
     * the user requested it through the `additional_formats` option, the additional
     * export formats (e.g. SRT subtitles) alongside it.
     *
     * To keep the result unambiguous, the client mirrors the Whisper bridge: when
     * no `additional_formats` option was requested it returns a plain `TextResult`
     * (readable through `ResultInterface::asText()`), and when additional formats were
     * requested it returns an `ObjectResult` wrapping a `Transcript` that carries both
     * the transcript text and the decoded export formats (readable through `asObject()`).
     */
    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $this->throwOnUnsuccessfulResponse($result);

        $data = $result->getData();
        $text = $data['text'] ?? '';

        if ([] === ($options['additional_formats'] ?? [])) {
            return new TextResult($text);
        }

        $additionalFormats = \is_array($data['additional_formats'] ?? null)
            ? array_values(array_map(AdditionalFormat::fromArray(...), $data['additional_formats']))
            : [];

        return new ObjectResult(new Transcript($text, $additionalFormats));
    }

    public function getTokenUsageExtractor(): null
    {
        return null;
    }

    /**
     * ElevenLabs expects the speech-to-text multipart fields to be sent as plain
     * strings: booleans as `true`/`false`, integers as their string representation
     * and array-shaped options (e.g. `additional_formats`) as a JSON-encoded string.
     */
    private function stringifySpeechToTextOption(int|string $key, mixed $value): string
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
