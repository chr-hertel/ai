<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech;

use Symfony\AI\Platform\Bridge\OpenAi\AbstractModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\EndpointHandlerTrait;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech;
use Symfony\AI\Platform\EndpointHandlerInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Self-contained handler for the OpenAI Text-to-Speech endpoint.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EndpointHandler extends AbstractModelClient implements EndpointHandlerInterface
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

    public function supports(Model $model, ?string $task = null): bool
    {
        return $model instanceof TextToSpeech;
    }

    public function request(Model $model, array|string $payload, array $options = []): ResultInterface
    {
        if (!isset($options['voice'])) {
            throw new InvalidArgumentException('The "voice" option is required for TextToSpeech requests.');
        }

        if (isset($options['stream_format']) || isset($options['stream'])) {
            throw new InvalidArgumentException('Streaming text to speech results is not supported yet.');
        }

        $input = \is_string($payload) ? $payload : ($payload['text'] ?? throw new InvalidArgumentException('The payload must contain a "text" key.'));

        $rawResult = new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/v1/audio/speech', self::getBaseUrl($this->region)), [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => array_merge($options, ['model' => $model->getName(), 'input' => $input]),
        ]));

        $result = $this->resultConverter->convert($rawResult, $options);
        $this->attachTokenUsage($result, $this->resultConverter->getTokenUsageExtractor(), $rawResult, $options);

        return $this->finalizeResult($result, $rawResult);
    }
}
