<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SpeechToTextClient implements ApiClientInterface
{
    use HttpStatusErrorHandlingTrait;

    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Mistral-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.mistral.ai',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechToText;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $body = array_merge($options, $payload, ['model' => $model->getName()]);

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/audio/transcriptions', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'body' => $body,
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): TextResult
    {
        $httpResponse = $result->getObject();

        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $httpResponse->getContent(false)));
        }

        $data = $result->getData();

        if (!isset($data['text'])) {
            throw new RuntimeException('Response does not contain transcription text.');
        }

        $textResult = new TextResult($data['text']);

        if (isset($data['usage'])) {
            $textResult->getMetadata()->add('usage', $data['usage']);
        }

        return $textResult;
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
