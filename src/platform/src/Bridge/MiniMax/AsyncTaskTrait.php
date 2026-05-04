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

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\BinaryResult;

/**
 * Polling and file download for MiniMax's asynchronous task endpoints
 * (async text-to-speech and video generation).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
trait AsyncTaskTrait
{
    /**
     * Delay, in seconds, between two polls of an asynchronous task.
     */
    private const POLL_INTERVAL = 1;

    /**
     * Polls an asynchronous task until it reaches a terminal state, then downloads the resulting file.
     *
     * @param array<string, mixed> $data
     */
    private function handleAsyncTask(array $data, string $queryPath, string $mimeType, int $maxPolls): BinaryResult
    {
        $taskId = $data['task_id'] ?? throw new RuntimeException('The MiniMax response does not contain a task identifier.');
        $fileId = $data['file_id'] ?? null;

        for ($poll = 0; $poll < $maxPolls; ++$poll) {
            $response = $this->httpClient->request('GET', \sprintf('%s/%s?task_id=%s', $this->endpoint, $queryPath, $taskId), [
                'auth_bearer' => $this->apiKey,
            ]);

            $this->guardResponseStatus($response);

            $status = $response->toArray(false);

            $fileId = $status['file_id'] ?? $fileId;
            $state = strtolower((string) ($status['status'] ?? ''));

            if ('success' === $state) {
                return new BinaryResult($this->download($fileId), $mimeType);
            }

            if (\in_array($state, ['fail', 'failed', 'expired'], true)) {
                throw new RuntimeException(\sprintf('The MiniMax task "%s" failed with status "%s".', $taskId, $status['status'] ?? ''));
            }

            $this->clock->sleep(self::POLL_INTERVAL);
        }

        throw new RuntimeException(\sprintf('The MiniMax task "%s" did not complete in time.', $taskId));
    }

    private function download(mixed $fileId): string
    {
        if (null === $fileId) {
            throw new RuntimeException('The MiniMax task did not return a file identifier.');
        }

        $response = $this->httpClient->request('GET', \sprintf('%s/files/retrieve?file_id=%s', $this->endpoint, $fileId), [
            'auth_bearer' => $this->apiKey,
        ]);

        $this->guardResponseStatus($response);

        $file = $response->toArray(false);

        $downloadUrl = $file['file']['download_url'] ?? throw new RuntimeException('The MiniMax file does not contain a download URL.');

        $download = $this->httpClient->request('GET', $downloadUrl);

        $this->guardResponseStatus($download);

        return $download->getContent();
    }
}
