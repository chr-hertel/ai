<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Deepgram /v1/listen client (speech-to-text).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ListenClient implements EndpointClientInterface
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    public const ENDPOINT = 'deepgram.listen';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $deepgramPayload = new DeepgramPayload($payload);

        unset($options['stream']);

        $query = [
            'model' => $model->getName(),
            ...$options,
        ];

        if ($deepgramPayload->isUrlBased()) {
            return new RawHttpResult($this->httpClient->request('POST', 'listen', [
                'query' => $query,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $this->encodeJsonBody([
                    'url' => $deepgramPayload->getAudioUrl(),
                ]),
            ]));
        }

        return new RawHttpResult($this->httpClient->request('POST', 'listen', [
            'query' => $query,
            'headers' => [
                'Content-Type' => $deepgramPayload->getAudioMimeType(),
            ],
            'body' => $this->resolveAudioBody($deepgramPayload),
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if (!$result instanceof RawHttpResult) {
            throw new RuntimeException(\sprintf('Unsupported raw result of type "%s".', $result::class));
        }

        $response = $result->getObject();

        if (200 !== $response->getStatusCode()) {
            $this->throwOnHttpError($response);

            throw new RuntimeException($this->extractErrorMessage($response));
        }

        return new TextResult($this->extractTranscript($result->getData()));
    }

    /**
     * Streams the audio from disk when possible to avoid materializing
     * large files in memory; falls back to the decoded base64 payload.
     *
     * @return resource|string
     */
    private function resolveAudioBody(DeepgramPayload $payload)
    {
        $path = $payload->getAudioPath();
        if (null !== $path && is_file($path) && is_readable($path)) {
            $stream = fopen($path, 'r');
            if (false !== $stream) {
                return $stream;
            }
        }

        return $payload->getAudioBinary();
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function extractTranscript(array $data): string
    {
        $results = $data['results'] ?? null;
        $channels = \is_array($results) ? ($results['channels'] ?? null) : null;
        if (!\is_array($channels)) {
            throw new RuntimeException('Unexpected Deepgram transcription response: the "results.channels" entry is missing.');
        }

        $transcripts = [];
        foreach ($channels as $channel) {
            if (!\is_array($channel)) {
                continue;
            }
            $alternatives = $channel['alternatives'] ?? null;
            if (!\is_array($alternatives) || !isset($alternatives[0]) || !\is_array($alternatives[0])) {
                continue;
            }
            $candidate = $alternatives[0]['transcript'] ?? null;
            if (\is_string($candidate) && '' !== $candidate) {
                $transcripts[] = $candidate;
            }
        }

        return implode(' ', $transcripts);
    }

    private function extractErrorMessage(ResponseInterface $response): string
    {
        try {
            $data = $response->toArray(false);
        } catch (JsonException) {
            return \sprintf('The Deepgram API returned a non-successful status code "%d".', $response->getStatusCode());
        }

        $message = $data['err_msg']
            ?? $data['error']
            ?? $data['reason']
            ?? $data['message']
            ?? null;

        if (\is_string($message) && '' !== $message) {
            return \sprintf('The Deepgram API returned an error: "%s".', $message);
        }

        return \sprintf('The Deepgram API returned a non-successful status code "%d".', $response->getStatusCode());
    }
}
