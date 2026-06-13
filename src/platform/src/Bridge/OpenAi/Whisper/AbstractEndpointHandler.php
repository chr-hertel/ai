<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Whisper;

use Symfony\AI\Platform\Bridge\OpenAi\AbstractModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\EndpointHandlerTrait;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\EndpointHandlerInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Shared request handling for the OpenAI audio (Whisper) endpoints.
 *
 * The transcription and translation endpoints share the same model and request
 * shape; they differ only in the URL and the task they handle.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractEndpointHandler extends AbstractModelClient implements EndpointHandlerInterface
{
    use EndpointHandlerTrait;

    private readonly ResultConverter $resultConverter;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $region = null,
    ) {
        $this->resultConverter = new ResultConverter();
        self::validateApiKey($apiKey);
    }

    public function request(Model $model, array|string $payload, array $options = []): ResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', static::class));
        }

        unset($options['task']);

        if ($options['verbose'] ?? false) {
            $options['response_format'] = 'verbose_json';
            unset($options['verbose']);
        }

        $rawResult = new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/v1/audio/%s', self::getBaseUrl($this->region), $this->endpoint()), [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'body' => array_merge($options, $payload, ['model' => $model->getName()]),
        ]));

        $result = $this->resultConverter->convert($rawResult, $options);

        return $this->finalizeResult($result, $rawResult);
    }

    /**
     * The OpenAI audio endpoint path segment, e.g. "transcriptions" or "translations".
     */
    abstract protected function endpoint(): string;
}
