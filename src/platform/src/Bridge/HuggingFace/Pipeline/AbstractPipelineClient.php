<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Pipeline;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Requests a task of the hf-inference service; subclasses read the response shape of their task.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractPipelineClient implements ApiClientInterface
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of the Hugging Face inference router, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://router.huggingface.co',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $url = $this->baseUrl.$this->getPath($model);

        if (\is_array($payload) && isset($payload['body'])) {
            return new RawHttpResult($this->httpClient->request('POST', $url, [
                'auth_bearer' => $this->apiKey,
                'headers' => $payload['headers'] ?? [],
                'body' => $payload['body'],
            ]));
        }

        $body = ['inputs' => $payload];
        if ([] !== $options) {
            $body['parameters'] = $options;
        }

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->encodeJsonBody($body),
        ]));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    protected function getPath(Model $model): string
    {
        return \sprintf('/hf-inference/models/%s', $model->getName());
    }

    /**
     * @return array<mixed>
     */
    protected function getResponseData(RawResultInterface $result): array
    {
        $response = $result->getObject();
        \assert($response instanceof ResponseInterface);

        $this->assertSuccessfulResponse($response);

        return $result->getData();
    }

    protected function assertSuccessfulResponse(ResponseInterface $response): void
    {
        $this->throwOnHttpError($response);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $response->getStatusCode(), $this->extractErrorMessage($response) ?? $response->getContent(false)));
        }
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        // hf-inference reports errors as a plain string or a list of strings
        $error = $data['error'] ?? null;
        if (\is_array($error)) {
            $error = $error[0] ?? $error['message'] ?? null;
        }

        return \is_string($error) ? $error : null;
    }
}
