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
use Symfony\AI\Platform\Bridge\Mistral\Ocr\ResultConverter;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Tac Tacelosky <tacman@gmail.com>
 */
final class OcrClient implements ApiClientInterface
{
    private readonly ResultConverter $resultConverter;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        $this->resultConverter = new ResultConverter();
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Ocr;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('The payload must be an array of normalized document content, but a string was given to "%s".', self::class));
        }

        return new RawHttpResult($this->httpClient->request('POST', 'https://api.mistral.ai/v1/ocr', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'document' => $payload,
            ]),
        ]));
    }

    public function convert(RawResultInterface $raw, array $options = []): ObjectResult
    {
        return $this->resultConverter->convert($raw, $options);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return $this->resultConverter->getTokenUsageExtractor();
    }
}
