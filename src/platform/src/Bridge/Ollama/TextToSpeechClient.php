<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Bridge\Ollama\TextToSpeech\Format;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TextToSpeechClient extends AbstractOllamaClient
{
    use JsonBodyEncodingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof TextToSpeech;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (($options['stream'] ?? false) || isset($options['stream_format'])) {
            throw new InvalidArgumentException('Streaming text to speech results is not supported yet.');
        }

        unset($options['stream'], $options['stream_format']);

        if (\is_string($payload)) {
            $input = $payload;
        } elseif (!\array_key_exists('text', $payload)) {
            throw new InvalidArgumentException('The payload must contain a "text" key.');
        } else {
            $input = $payload['text'];
        }

        if (!\is_string($input)) {
            throw new InvalidArgumentException('The payload "text" key must be a string.');
        }

        return new RawHttpResult($this->httpClient->request('POST', '/v1/audio/speech', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->encodeJsonBody(array_merge($options, [
                'model' => $model->getName(),
                'input' => $input,
            ])),
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        $this->throwOnError($result);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('The Ollama Text-to-Speech API returned an error: "%s"', $response->getContent(false)));
        }

        $mimeType = $response->getHeaders(false)['content-type'][0] ?? null;

        return new BinaryResult($response->getContent(), null !== $mimeType ? explode(';', $mimeType, 2)[0] : match ($options['response_format'] ?? Format::WAV) {
            Format::PCM => 'audio/pcm',
            Format::WAV => 'audio/wav',
            default => null,
        });
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    protected function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        $error = $data['error'] ?? null;

        if (\is_string($error)) {
            return $error;
        }

        if (\is_array($error) && \is_string($error['message'] ?? null)) {
            return $error['message'];
        }

        return \is_string($data['message'] ?? null) ? $data['message'] : null;
    }
}
