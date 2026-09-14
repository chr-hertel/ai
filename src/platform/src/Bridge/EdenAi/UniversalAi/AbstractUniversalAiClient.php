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

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\EdenAi\ErrorHandlingTrait;
use Symfony\AI\Platform\Bridge\EdenAi\ImageGeneration;
use Symfony\AI\Platform\Bridge\EdenAi\Tts;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client for the Eden AI expert models, exposed through the /v3/universal-ai endpoints. A client
 * per model family, as the output each of them answers with has its own shape.
 *
 * The payload is either the normalized array coming from the bridge normalizers or a
 * plain string: a direct file URL or a file ID from POST /v3/upload for file-based
 * models, the text to process for text-based models (TTS, image generation). Binary
 * content (a "file_data" payload entry) is transparently uploaded through /v3/upload
 * first. Any option that is not a root-level request field is forwarded as part of the
 * "input" object (e.g. "language", "document_type" or "voice").
 *
 * @see https://www.edenai.co/docs
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
abstract class AbstractUniversalAiClient implements ApiClientInterface
{
    use ErrorHandlingTrait;

    /**
     * Options that belong to the root of the request body instead of the "input" object.
     *
     * "webhook_receiver" and "user_webhook_parameters" are only honored by the
     * asynchronous endpoint, which extends the synchronous body with them.
     *
     * @var list<string>
     */
    private const ROOT_OPTIONS = ['fallbacks', 'provider_params', 'show_original_response', 'user_webhook_parameters', 'webhook_receiver'];

    private readonly FileUploader $fileUploader;

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
        ?FileUploader $fileUploader = null,
    ) {
        $this->fileUploader = $fileUploader ?? new FileUploader($httpClient, $baseUrl, $apiKey);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.$this->getPath(), [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $this->createRequestBody($model, $payload, $options),
        ]));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    protected function getPath(): string
    {
        return '/v3/universal-ai';
    }

    /**
     * @param list<int> $expectedStatusCodes
     *
     * @return array<string, mixed>
     */
    protected function getResponseData(RawResultInterface $result, array $expectedStatusCodes = [200]): array
    {
        $response = $result->getObject();
        \assert($response instanceof ResponseInterface);

        $this->assertSuccessfulResponse($response, $expectedStatusCodes);

        $data = $result->getData();

        if ('fail' === ($data['status'] ?? null)) {
            throw new RuntimeException(\sprintf('Eden AI request failed: "%s"', $data['error']['message'] ?? 'Unknown error'));
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function createRequestBody(Model $model, array|string $payload, array $options): array
    {
        $input = \is_string($payload) ? [$this->getStringPayloadKey($model) => $payload] : $payload;

        if (isset($input['file_data'])) {
            $fileData = $input['file_data'];
            unset($input['file_data']);

            $input['file'] = $this->uploadBinaryInput($fileData);
        }

        $rootOptions = array_intersect_key($options, array_flip(self::ROOT_OPTIONS));
        $inputOptions = array_diff_key($options, array_flip(self::ROOT_OPTIONS));

        return array_merge($rootOptions, [
            'model' => $model->getName(),
            'input' => array_merge($inputOptions, $input),
        ]);
    }

    /**
     * Uploads a normalized binary content entry and returns the resulting file ID.
     *
     * The entry is validated first: an unusable one would otherwise be uploaded as an
     * empty file and then submitted as a billable request.
     */
    private function uploadBinaryInput(mixed $fileData): string
    {
        if (!\is_array($fileData)) {
            throw new RuntimeException('Eden AI binary input must be an array.');
        }

        $data = $fileData['data'] ?? null;

        if (!\is_string($data) || '' === $data) {
            throw new RuntimeException('Eden AI binary input does not contain any data.');
        }

        $decoded = base64_decode($data, true);

        if (false === $decoded || '' === $decoded) {
            throw new RuntimeException('Eden AI binary input is not valid base64 content.');
        }

        $filename = $fileData['filename'] ?? null;
        $format = $fileData['format'] ?? null;

        return $this->fileUploader->upload(
            $decoded,
            \is_string($filename) ? $filename : null,
            \is_string($format) ? $format : 'application/octet-stream',
        );
    }

    private function getStringPayloadKey(Model $model): string
    {
        if ($model instanceof Tts || $model instanceof ImageGeneration) {
            return 'text';
        }

        return 'file';
    }
}
