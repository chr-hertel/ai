<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Gpt;

use Symfony\AI\Platform\Bridge\OpenAi\AbstractModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\EndpointHandlerTrait;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\EndpointHandlerInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Self-contained handler for the OpenAI Responses endpoint.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EndpointHandler extends AbstractModelClient implements EndpointHandlerInterface
{
    use EndpointHandlerTrait;
    use JsonBodyEncodingTrait;

    private readonly EventSourceHttpClient $httpClient;
    private readonly ResultConverter $resultConverter;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $region = null,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->resultConverter = new ResultConverter();
        self::validateApiKey($apiKey);
    }

    public function supports(Model $model, ?string $task = null): bool
    {
        return $model instanceof Gpt;
    }

    public function request(Model $model, array|string $payload, array $options = []): ResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        // OpenAI performs automatic prompt caching; no explicit cache_control
        // annotation is needed and cacheRetention is not an OpenAI concept.
        // Strip it so it is never forwarded to the Responses API.
        unset($options['cacheRetention']);

        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $schema = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema'];
            $options['text']['format'] = $schema;
            $options['text']['format']['name'] = $schema['name'];
            $options['text']['format']['type'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['type'];

            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        $rawResult = new RawHttpResult($this->httpClient->request('POST', self::getBaseUrl($this->region).'/v1/responses', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->encodeJsonBody(array_merge($options, ['model' => $model->getName()], $payload)),
        ]));

        $result = $this->resultConverter->convert($rawResult, $options);
        $this->attachTokenUsage($result, $this->resultConverter->getTokenUsageExtractor(), $rawResult, $options);

        return $this->finalizeResult($result, $rawResult);
    }
}
