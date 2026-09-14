<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client for the Higgsfield API (https://higgsfield.ai).
 *
 * Submits a generation and answers with a handle on its request; resolving it is the job of
 * {@see HiggsfieldJobClient}.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class GenerationClient implements ApiClientInterface
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    /**
     * @param string $provider the name stamped onto the handles of the generations this client starts
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $provider = 'higgsfield',
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Higgsfield;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $endpoint = ltrim($model->getName(), '/');

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('/%s', $endpoint), [
            'body' => $this->encodeJsonBody($this->createInput($payload, $options)),
            'headers' => ['Content-Type' => 'application/json'],
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): JobResult
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        $this->throwOnHttpError($response);

        $data = $response->toArray(false);

        $requestId = $data['request_id'] ?? throw new RuntimeException(\sprintf('Higgsfield API error: "%s".', $this->extractErrorMessage($response) ?? 'Unknown error'));

        return new JobResult(new JobHandle(
            (string) $requestId,
            ['status_path' => \sprintf('requests/%s/status', $requestId)],
            $this->provider,
            HiggsfieldJobClient::DEFAULT_MAX_DURATION,
            HiggsfieldJobClient::DEFAULT_POLL_INTERVAL,
        ));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    /**
     * Overrides the shared lookup: Higgsfield puts its message into `detail` rather than `error.message`.
     */
    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            return HiggsfieldJobClient::extractError($response->toArray(false));
        } catch (DecodingExceptionInterface) {
            return null;
        }
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     *
     * @return array<string, mixed>
     */
    private function createInput(array|string $payload, array $options): array
    {
        if (\is_string($payload)) {
            return ['prompt' => $payload, ...$options];
        }

        // Image content normalized by the ImageNormalizer, e.g. for image-to-video generation.
        if (isset($payload['type'], $payload['mime_type'], $payload['data']) && 'image' === $payload['type']) {
            return ['image_url' => $this->uploadFile($payload['mime_type'], base64_decode($payload['data'])), ...$options];
        }

        // Text content normalized to ['text' => '...'] by the default contract.
        if (isset($payload['text'])) {
            return ['prompt' => $payload['text'], ...$options];
        }

        return [...$payload, ...$options];
    }

    /**
     * The API accepts input media only as HTTP(S) URLs, so files go to a pre-signed upload URL first.
     */
    private function uploadFile(string $contentType, string $content): string
    {
        $upload = $this->httpClient->request('POST', '/files/generate-upload-url', [
            'json' => ['content_type' => $contentType],
        ])->toArray();

        $this->httpClient->request('PUT', $upload['upload_url'], [
            'body' => $content,
            'headers' => $upload['upload_headers'] ?? ['Content-Type' => $contentType],
        ])->getContent();

        return $upload['public_url'];
    }
}
