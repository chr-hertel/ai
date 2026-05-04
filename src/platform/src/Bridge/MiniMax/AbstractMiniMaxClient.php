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

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Shared MiniMax HTTP plumbing: bearer auth, JSON body encoding and HTTP status mapping.
 *
 * Each concrete client owns exactly one MiniMax endpoint, so the response shape is known
 * statically instead of being recovered from the request URL after the fact.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractMiniMaxClient implements EndpointClientInterface
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] protected readonly string $apiKey,
        protected readonly string $endpoint = 'https://api.minimax.io/v1',
        protected readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    /**
     * @param array<string, mixed> $json
     */
    protected function post(string $path, array $json): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/%s', $this->endpoint, $path), [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->encodeJsonBody($json),
        ]));
    }

    /**
     * Extracts the plain text from either a raw string payload or the array shape produced by the
     * Text content normalizer (`['type' => 'text', 'text' => '...']`).
     *
     * @param array<string, mixed>|string $payload
     */
    protected function extractText(array|string $payload): string
    {
        if (\is_string($payload)) {
            return $payload;
        }

        if (\array_key_exists('text', $payload) && \is_string($payload['text'])) {
            return $payload['text'];
        }

        throw new InvalidArgumentException('The payload must be a string or contain a "text" key.');
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function decodeHexAudio(array $data): string
    {
        $audio = $data['data']['audio'] ?? throw new RuntimeException('The MiniMax response does not contain any audio.');

        $decoded = hex2bin($audio);

        if (false === $decoded) {
            throw new RuntimeException('The MiniMax audio payload is not valid hexadecimal.');
        }

        return $decoded;
    }

    protected function guardHttpStatus(RawResultInterface $result): void
    {
        if ($result instanceof RawHttpResult) {
            $this->throwOnHttpError($result->getObject());
        }
    }

    /**
     * `HttpStatusErrorHandlingTrait::throwOnHttpError()` is private to this class;
     * subclasses reach it through here.
     */
    protected function guardResponseStatus(ResponseInterface $response): void
    {
        $this->throwOnHttpError($response);
    }
}
