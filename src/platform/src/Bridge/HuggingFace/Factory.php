<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Bridge\HuggingFace\Contract\HuggingFaceContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider as PlatformProvider;
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
     * @param ?string          $provider Inference provider for chat completion, Hugging Face picks one when null
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        ?string $provider = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'huggingface',
        string $baseUrl = 'https://router.huggingface.co',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new PlatformProvider(
            $name,
            [
                new ChatCompletionsClient(new HttpTransport($httpClient, $baseUrl, $apiKey), $provider),
                new Pipeline\ClassificationClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\EmbeddingsClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\FillMaskClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\ImageSegmentationClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\ObjectDetectionClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\QuestionAnsweringClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\RerankerClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\SpeechRecognitionClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\SummarizationClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\TableQuestionAnsweringClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\TextToImageClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\TokenClassificationClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\TranslationClient($httpClient, $apiKey, $baseUrl),
                new Pipeline\ZeroShotClassificationClient($httpClient, $apiKey, $baseUrl),
            ],
            $modelCatalog,
            $contract ?? HuggingFaceContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param ?string          $provider Inference provider for chat completion, Hugging Face picks one when null
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        ?string $provider = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'huggingface',
        ?ModelRouterInterface $modelRouter = null,
        string $baseUrl = 'https://router.huggingface.co',
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $provider, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name, $baseUrl)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }

    /**
     * @param ?string $provider Inference provider whose chat models the catalog returns
     */
    public static function createModelCatalog(
        #[\SensitiveParameter] string $apiKey,
        ?string $provider = null,
        ?HttpClientInterface $httpClient = null,
    ): ModelCatalogInterface {
        return new ModelApiCatalog(new ApiClient($httpClient, $apiKey), $provider);
    }
}
