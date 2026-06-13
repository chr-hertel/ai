<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\DallE;

use Symfony\AI\Platform\Bridge\OpenAi\AbstractModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\DallE;
use Symfony\AI\Platform\Bridge\OpenAi\EndpointHandlerTrait;
use Symfony\AI\Platform\EndpointHandlerInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Self-contained handler for the OpenAI Image generation endpoint.
 *
 * @see https://platform.openai.com/docs/api-reference/images/create
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
        return $model instanceof DallE;
    }

    public function request(Model $model, array|string $payload, array $options = []): ResultInterface
    {
        $rawResult = new RawHttpResult($this->httpClient->request('POST', self::getBaseUrl($this->region).'/v1/images/generations', [
            'auth_bearer' => $this->apiKey,
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'prompt' => $payload,
            ]),
        ]));

        $result = $this->resultConverter->convert($rawResult, $options);
        $this->attachTokenUsage($result, $this->resultConverter->getTokenUsageExtractor(), $rawResult, $options);

        return $this->finalizeResult($result, $rawResult);
    }
}
