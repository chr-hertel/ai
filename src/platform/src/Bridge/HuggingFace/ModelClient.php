<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClient implements ApiClientInterface
{
    use JsonBodyEncodingTrait;

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;
    private readonly ResultConverter $resultConverter;

    /**
     * @param string $baseUrl Base URL of the HuggingFace inference router, with or without a trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $provider,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://router.huggingface.co',
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->resultConverter = new ResultConverter();
    }

    public function supports(Model $model): bool
    {
        return true;
    }

    /**
     * The difference in HuggingFace here is that we treat the payload as the options for the request not only the body.
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $provider = $options['provider'] ?? $this->provider;
        $task = $options['task'] ?? null;
        unset($options['task'], $options['provider']);

        return new RawHttpResult($this->httpClient->request('POST', $this->getUrl($model, $provider, $task), [
            'auth_bearer' => $this->apiKey,
            ...$this->getPayload($model, $payload, $options, $task, $provider),
        ]));
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        return $this->resultConverter->convert($raw, $options);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return $this->resultConverter->getTokenUsageExtractor();
    }

    private function getUrl(Model $model, string $provider, ?string $task): string
    {
        if (Task::CHAT_COMPLETION === $task) {
            if (Provider::HF_INFERENCE === $provider) {
                return \sprintf('%s/%s/models/%s/v1/chat/completions', $this->baseUrl, $provider, $model->getName());
            }

            // Third-party providers use OpenAI-compatible endpoint
            return \sprintf('%s/%s/v1/chat/completions', $this->baseUrl, $provider);
        }

        return \sprintf('%s/%s/models/%s', $this->baseUrl, $provider, $model->getName());
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function getPayload(Model $model, array|string $payload, array $options, ?string $task, string $provider): array
    {
        // Text ranking: convert {query, texts} into text/text_pair pairs for the HF text-classification pipeline
        if (Task::TEXT_RANKING === $task && \is_array($payload) && isset($payload['query'], $payload['texts'])) {
            $inputs = [];
            foreach ($payload['texts'] as $text) {
                $inputs[] = ['text' => $payload['query'], 'text_pair' => $text];
            }

            return [
                'body' => $this->encodeJsonBody(['inputs' => $inputs]),
                'headers' => ['Content-Type' => 'application/json'],
            ];
        }

        // Expect JSON input if string or not
        if (\is_string($payload) || !(isset($payload['body']) || isset($payload['json']))) {
            $payload = ['json' => ['inputs' => $payload]];

            if ([] !== $options) {
                $payload['json']['parameters'] = $options;
            }
        }

        // Merge options into JSON payload
        if (isset($payload['json'])) {
            $payload['json'] = array_merge($payload['json'], $options);
        }

        // Third-party providers need the model name in the JSON body
        if (Task::CHAT_COMPLETION === $task && Provider::HF_INFERENCE !== $provider && isset($payload['json'])) {
            $payload['json']['model'] = $model->getName();
        }

        // Encode the JSON payload ourselves to tolerate malformed UTF-8 (e.g. raw tool output)
        if (isset($payload['json'])) {
            $payload['body'] = $this->encodeJsonBody($payload['json']);
            unset($payload['json']);
        }

        $payload['headers'] ??= ['Content-Type' => 'application/json'];

        return $payload;
    }
}
