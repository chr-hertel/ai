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
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        string $endpoint = 'https://api.minimax.io/v1',
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'minimax',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $jobClient = self::createJobClient($apiKey, $httpClient, $endpoint, $name);

        return new Provider(
            $name,
            [
                new ChatCompletionsClient($httpClient, $apiKey, $endpoint),
                new SpeechClient($httpClient, $apiKey, $endpoint, $jobClient),
                new ImageClient($httpClient, $apiKey, $endpoint),
                new MusicClient($httpClient, $apiKey, $endpoint),
                new VideoClient($httpClient, $apiKey, $endpoint, $jobClient),
            ],
            $modelCatalog,
            $contract ?? MiniMaxContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * The client resolving the jobs this bridge hands out - typically in a worker picking up a
     * stored handle, without a provider or platform at hand.
     *
     * @param string $name the provider name stated on the handles this client creates
     */
    public static function createJobClient(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        string $endpoint = 'https://api.minimax.io/v1',
        string $name = 'minimax',
    ): MiniMaxJobClient {
        return new MiniMaxJobClient($httpClient ?? new EventSourceHttpClient(), $apiKey, $endpoint, $name);
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        string $endpoint = 'https://api.minimax.io/v1',
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
