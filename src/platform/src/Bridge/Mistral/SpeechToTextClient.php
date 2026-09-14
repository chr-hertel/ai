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
use Symfony\AI\Platform\Bridge\Mistral\SpeechToText\ResultConverter;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
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
    private readonly string $baseUrl;
    private readonly ResultConverter $resultConverter;

    /**
     * @param string $baseUrl Base URL of a Mistral-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.mistral.ai',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->resultConverter = new ResultConverter();
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

    public function convert(RawResultInterface $raw, array $options = []): TextResult
    {
        return $this->resultConverter->convert($raw, $options);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return $this->resultConverter->getTokenUsageExtractor();
    }
}
