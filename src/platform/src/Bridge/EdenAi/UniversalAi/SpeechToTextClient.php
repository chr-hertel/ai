<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\UniversalAi;

use Symfony\AI\Platform\Bridge\EdenAi\EdenAiJobClient;
use Symfony\AI\Platform\Bridge\EdenAi\ResultMetadataTrait;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for the Eden AI speech-to-text expert models, exposed through the asynchronous
 * /v3/universal-ai/async endpoint.
 *
 * Submitting is a single request, whose answer is one of two things.
 *
 * Providers that transcribe fast enough put the text straight into the 202, and the invocation is
 * done. The others report a job still running, and the invocation yields a {@see JobResult} whose
 * handle is resolved later through {@see EdenAiJobClient} - from this process via
 * {@see \Symfony\AI\Platform\Job\JobRunner}, or from a worker that picked the handle up from
 * storage.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class SpeechToTextClient extends AbstractUniversalAiClient
{
    use ResultMetadataTrait;

    /**
     * How long a speech-to-text job may reasonably take at Eden AI, in seconds. Stated on the
     * handle so a caller who knows nothing about the provider's timings does not have to guess.
     *
     * Generous on purpose: this is a gateway, so the wait is the provider's own transcription time
     * plus however long Eden AI's queue is, and measured runs stay in `processing` for minutes even
     * for a few seconds of audio. It is a budget, not a promise - a caller who cannot wait that
     * long passes its own `maxDuration`, or keeps the handle and comes back to it later.
     */
    private const MAX_DURATION = 600;

    private readonly EdenAiJobClient $jobClient;

    /**
     * @param EdenAiJobClient|null $jobClient creates the handles of the jobs this client hands
     *                                        out, so they name the provider that issued them
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
        #[\SensitiveParameter] string $apiKey,
        ?EdenAiJobClient $jobClient = null,
        ?FileUploader $fileUploader = null,
    ) {
        parent::__construct($httpClient, $baseUrl, $apiKey, $fileUploader);

        $this->jobClient = $jobClient ?? new EdenAiJobClient($httpClient, $apiKey, $baseUrl);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechToText;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        // The asynchronous endpoint answers 202 when it accepts the job, and 200 when polled.
        $data = $this->getResponseData($result, [200, 202]);

        if ('processing' === ($data['status'] ?? null)) {
            return $this->startJob($data);
        }

        if (!isset($data['output']['text']) || !\is_string($data['output']['text'])) {
            throw new RuntimeException('Response does not contain text.');
        }

        $textResult = new TextResult($data['output']['text']);

        $this->attachEdenAiMetadata($textResult, $data, [
            'diarization' => $data['output']['diarization'] ?? null,
        ]);

        return $textResult;
    }

    protected function getPath(): string
    {
        return '/v3/universal-ai/async';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function startJob(array $data): JobResult
    {
        $publicId = $data['public_id'] ?? null;

        if (!\is_string($publicId) || '' === $publicId) {
            throw new RuntimeException('Eden AI accepted the job but did not return a public_id to resolve it with.');
        }

        return new JobResult($this->jobClient->createHandle($publicId, [
            'subfeature' => 'speech_to_text_async',
        ], self::MAX_DURATION));
    }
}
