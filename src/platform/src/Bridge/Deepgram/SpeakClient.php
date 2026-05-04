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
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Deepgram /v1/speak client (text-to-speech).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SpeakClient implements EndpointClientInterface
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    public const ENDPOINT = 'deepgram.speak';

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

        // "stream" is an SDK-internal flag consumed by the result converter, not a Deepgram query param
        $stream = true === ($options['stream'] ?? false);
        unset($options['stream']);

        return new RawHttpResult($this->httpClient->request('POST', 'speak', [
            'buffer' => !$stream,
            'query' => [
                'model' => $model->getName(),
                ...$options,
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->encodeJsonBody([
                'text' => $deepgramPayload->asTextToSpeechPayload(),
            ]),
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

        if (true === ($options['stream'] ?? false)) {
            return new StreamResult($this->streamBinary($response));
        }

        $contentType = $response->getHeaders(false)['content-type'][0] ?? 'audio/mpeg';

        return new BinaryResult($response->getContent(), $contentType);
    }

    private function streamBinary(ResponseInterface $response): \Generator
    {
        foreach ($this->httpClient->stream($response) as $chunk) {
            if ($chunk->isFirst() || $chunk->isLast()) {
                continue;
            }

            $content = $chunk->getContent();
            if ('' === $content) {
                continue;
            }

            yield new BinaryDelta($content);
        }
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
