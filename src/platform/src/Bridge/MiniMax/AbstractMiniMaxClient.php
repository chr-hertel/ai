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

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
abstract class AbstractMiniMaxClient implements ApiClientInterface
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] protected readonly string $apiKey,
        protected readonly string $endpoint = 'https://api.minimax.io/v1',
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
     * MiniMax reports a rejected request with HTTP 200 and the reason in `base_resp`, so the status
     * code alone does not tell whether a response carries a result. Observed against the live API:
     * an unknown voice on `t2a_v2` yields `2054 "voice id not exist"`, an unsupported model on
     * `image_generation` yields `2013 "invalid params, ..."`, and an empty account yields
     * `1008 "insufficient balance"` - all with HTTP 200, all otherwise indistinguishable from a
     * success apart from the payload keys being absent. Every successful response carries
     * `status_code: 0`.
     *
     * @param array<string, mixed> $data
     */
    protected function throwOnBusinessError(array $data): void
    {
        $statusCode = $data['base_resp']['status_code'] ?? 0;

        if (0 === $statusCode) {
            return;
        }

        throw new RuntimeException(\sprintf('MiniMax rejected the request: "%s" (status code "%s").', $data['base_resp']['status_msg'] ?? 'unknown error', $statusCode));
    }

    /**
     * MiniMax answered with a task identifier instead of a payload, so the invocation produces a
     * reference to that task rather than a result. Resolving it - polling, and downloading the file
     * it produces - is the job of {@see MiniMaxJobClient}; the handle carries what that client needs
     * to know about the endpoint the task came from.
     *
     * @param array<string, mixed> $data
     * @param int                  $maxDuration   how long this endpoint may reasonably take, in seconds
     * @param string|null          $archiveMember file extension to unpack from the downloaded tar,
     *                                            or null when the download is the payload itself
     */
    protected function startJob(array $data, string $queryPath, string $mimeType, int $maxDuration, ?string $archiveMember = null): JobResult
    {
        $taskId = $data['task_id'] ?? throw new RuntimeException('The MiniMax response does not contain a task identifier.');

        return new JobResult(new JobHandle((string) $taskId, [
            'query_path' => $queryPath,
            'mime_type' => $mimeType,
            'archive_member' => $archiveMember,
            'file_id' => $data['file_id'] ?? null,
        ], maxDuration: $maxDuration));
    }
}
