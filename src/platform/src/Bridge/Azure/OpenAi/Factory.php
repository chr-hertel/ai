<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\OpenAi;

use Symfony\AI\Platform\Bridge\Azure\Transport\AzureTransport;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\OpenAiContract;
use Symfony\AI\Platform\Bridge\OpenAi\EmbeddingsClient;
use Symfony\AI\Platform\Bridge\OpenAi\ResponsesClient;
use Symfony\AI\Platform\Bridge\OpenAi\TranscriptionClient;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        string $baseUrl,
        string $deployment,
        string $apiVersion,
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'azure-openai',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        // The OpenAI contract handlers are reused verbatim; everything Azure does
        // differently — api-key auth, the resource URL, the api-version and the
        // deployment indirection — lives in the transport. The catalog maps GPT and
        // embedding models onto their Responses/Generic classes, so each client is
        // told which family it serves here.
        $transport = new AzureTransport($httpClient, $baseUrl, $deployment, $apiVersion, $apiKey);
        $clients = [
            new ResponsesClient($transport, ResponsesModel::class),
            new EmbeddingsClient($transport, EmbeddingsModel::class),
            new TranscriptionClient($transport, Whisper::class),
        ];

        return new Provider(
            $name,
            $clients,
            $clients,
            $modelCatalog,
            $contract ?? OpenAiContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        string $baseUrl,
        string $deployment,
        string $apiVersion,
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'azure-openai',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($baseUrl, $deployment, $apiVersion, $apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
