<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter;

use Symfony\AI\Platform\Bridge\Generic;
use Symfony\AI\Platform\Bridge\OpenRouter\Rerank\Client as RerankClient;
use Symfony\AI\Platform\Bridge\OpenRouter\Speech\Client as SpeechClient;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author rglozman
 */
final class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'openrouter',
        string $baseUrl = 'https://openrouter.ai/api',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        $transport = new Generic\Transport\HttpTransport($httpClient, $baseUrl, $apiKey);

        return new Provider($name, [
            new Generic\ChatCompletionsClient($transport, '/v1/chat/completions'),
            new Generic\EmbeddingsClient($transport, '/v1/embeddings'),
            new RerankClient($httpClient, $apiKey, $baseUrl),
            new SpeechClient($httpClient, $apiKey, $baseUrl),
        ], $modelCatalog, $contract, $eventDispatcher);
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'openrouter',
        ?ModelRouterInterface $modelRouter = null,
        string $baseUrl = 'https://openrouter.ai/api',
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name, $baseUrl)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
