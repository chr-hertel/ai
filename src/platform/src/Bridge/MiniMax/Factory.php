<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax;

use Symfony\AI\Platform\Bridge\MiniMax\Contract\MiniMaxContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Factory
{
    private const DEFAULT_ENDPOINT = 'https://api.minimax.io/v1';

    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        string $endpoint = self::DEFAULT_ENDPOINT,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'minimax',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Provider(
            $name,
            [
                new ChatCompletionsClient($httpClient, $apiKey, $endpoint),
                new SpeechClient($httpClient, $apiKey, $endpoint, $name),
                new ImageClient($httpClient, $apiKey, $endpoint),
                new MusicClient($httpClient, $apiKey, $endpoint),
                new VideoClient($httpClient, $apiKey, $endpoint, $name),
            ],
            $modelCatalog,
            $contract ?? MiniMaxContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * The client resolving the jobs this bridge hands out - typically in a worker picking up a
     * stored handle, without a provider or platform at hand.
     */
    public static function createJobClient(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        string $endpoint = self::DEFAULT_ENDPOINT,
    ): MiniMaxJobClient {
        return new MiniMaxJobClient($httpClient ?? new EventSourceHttpClient(), $apiKey, $endpoint);
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        string $endpoint = self::DEFAULT_ENDPOINT,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'minimax',
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $httpClient, $endpoint, $modelCatalog, $contract, $eventDispatcher, $name)],
            new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
