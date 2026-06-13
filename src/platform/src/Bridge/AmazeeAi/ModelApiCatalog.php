<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AmazeeAi;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\AI\Platform\Task;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Model catalog that discovers available models from the amazee.ai
 * LiteLLM /model/info endpoint.
 *
 * Maps each model to CompletionsModel or EmbeddingsModel based on the
 * mode field, so the Generic platform's ModelClients can route requests
 * correctly.
 */
final class ModelApiCatalog extends AbstractModelCatalog
{
    private bool $modelsAreLoaded = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
    ) {
        $this->models = [];
    }

    public function getModel(string $modelName): Model
    {
        $this->preloadRemoteModels();

        return parent::getModel($modelName);
    }

    /**
     * @return array<string, array{class: class-string, tasks?: list<Task>, input?: list<Modality>, output?: list<Modality>, features?: list<Feature>}>
     */
    public function getModels(): array
    {
        $this->preloadRemoteModels();

        return parent::getModels();
    }

    private function preloadRemoteModels(): void
    {
        if ($this->modelsAreLoaded) {
            return;
        }

        $this->modelsAreLoaded = true;
        $this->models = [...$this->models, ...$this->fetchRemoteModels()];
    }

    /**
     * @return iterable<string, array{class: class-string<Model>, tasks: list<Task>, input: list<Modality>, output: list<Modality>, features: list<Feature>}>
     */
    private function fetchRemoteModels(): iterable
    {
        $response = $this->httpClient->request('GET', $this->baseUrl.'/model/info', [
            'headers' => array_filter([
                'Authorization' => $this->apiKey ? 'Bearer '.$this->apiKey : null,
            ]),
        ]);

        foreach ($response->toArray()['data'] ?? [] as $modelInfo) {
            $name = $modelInfo['model_name'] ?? null;
            if (null === $name) {
                continue;
            }

            $info = $modelInfo['model_info'] ?? [];
            $mode = $info['mode'] ?? null;

            if ('embedding' === $mode) {
                yield $name => ['class' => EmbeddingsModel::class] + $this->buildEmbeddingProfile($info);
            } else {
                yield $name => ['class' => CompletionsModel::class] + $this->buildCompletionsProfile($info);
            }
        }
    }

    /**
     * @param array<string, mixed> $info
     *
     * @return array{tasks: list<Task>, input: list<Modality>, output: list<Modality>, features: list<Feature>}
     */
    private function buildEmbeddingProfile(array $info): array
    {
        return [
            'tasks' => [Task::EMBEDDING],
            'input' => [Modality::TEXT],
            'output' => [],
            'features' => [],
        ];
    }

    /**
     * @param array<string, mixed> $info
     *
     * @return array{tasks: list<Task>, input: list<Modality>, output: list<Modality>, features: list<Feature>}
     */
    private function buildCompletionsProfile(array $info): array
    {
        $input = [Modality::TEXT];
        $features = [Feature::STREAMING];

        if ($info['supports_image_input'] ?? false) {
            $input[] = Modality::IMAGE;
        }
        if ($info['supports_audio_input'] ?? false) {
            $input[] = Modality::AUDIO;
        }
        if ($info['supports_tool_calling'] ?? $info['supports_function_calling'] ?? false) {
            $features[] = Feature::TOOL_CALLING;
        }
        if ($info['supports_response_schema'] ?? false) {
            $features[] = Feature::STRUCTURED_OUTPUT;
        }

        return [
            'tasks' => [Task::TEXT_GENERATION],
            'input' => $input,
            'output' => [Modality::TEXT],
            'features' => $features,
        ];
    }
}
