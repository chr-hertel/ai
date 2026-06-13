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

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ModelApiCatalog extends AbstractOpenRouterModelCatalog
{
    protected bool $modelsAreLoaded = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    public function getModel(string $modelName): Model
    {
        $this->preloadRemoteModels();

        return parent::getModel($modelName);
    }

    public function getModels(): array
    {
        $this->preloadRemoteModels();

        return parent::getModels();
    }

    protected function preloadRemoteModels(): void
    {
        if (!$this->modelsAreLoaded) {
            $this->models = [
                ...$this->models,
                ...$this->fetchRemoteModels(),
                ...$this->fetchRemoteEmbeddings(),
            ];
            ksort($this->models);
            $this->modelsAreLoaded = true;
        }
    }

    /**
     * @return iterable<string, array{class: class-string<Model>, tasks: list<Task>, input: list<Modality>, output: list<Modality>, features: list<Feature>}>
     */
    protected function fetchRemoteModels(): iterable
    {
        $responseModels = $this->httpClient->request('GET', 'https://openrouter.ai/api/v1/models');
        foreach ($responseModels->toArray()['data'] as $model) {
            $input = [];
            $output = [];
            $features = [];

            foreach ($model['architecture']['input_modalities'] as $inputModality) {
                switch ($inputModality) {
                    case 'text':
                        $input[] = Modality::TEXT;
                        break;
                    case 'image':
                        $input[] = Modality::IMAGE;
                        break;
                    case 'audio':
                        $input[] = Modality::AUDIO;
                        break;
                    case 'file':
                        $input[] = Modality::PDF;
                        break;
                    case 'video':
                        $input[] = Modality::VIDEO;
                        break;
                    default:
                        throw new InvalidArgumentException('Unknown model '.$inputModality.' input modality.', 1763717587);
                }
            }

            $tasks = [Task::TEXT_GENERATION];
            foreach ($model['architecture']['output_modalities'] as $outputModality) {
                switch ($outputModality) {
                    case 'text':
                        $output[] = Modality::TEXT;
                        break;
                    case 'image':
                        $output[] = Modality::IMAGE;
                        $tasks[] = Task::IMAGE_GENERATION;
                        break;
                    case 'audio':
                        $output[] = Modality::AUDIO;
                        $tasks[] = Task::SPEECH_SYNTHESIS;
                        break;
                    default:
                        throw new InvalidArgumentException('Unknown model '.$outputModality.' output modality.', 1763717588);
                }
            }

            // Streaming is allowed for any model: https://openrouter.ai/docs/api/reference/streaming
            $features[] = Feature::STREAMING;

            if (\in_array('structured_outputs', $model['supported_parameters'] ?? [])) {
                $features[] = Feature::STRUCTURED_OUTPUT;
            }

            if (\in_array('tool_choice', $model['supported_parameters'] ?? [])) {
                $features[] = Feature::TOOL_CALLING;
            }

            yield $model['id'] => [
                'class' => CompletionsModel::class,
                'tasks' => $tasks,
                'input' => $input,
                'output' => $output,
                'features' => $features,
            ];
        }
    }

    /**
     * @return iterable<string, array{class: class-string<EmbeddingsModel>, tasks: list<Task>, input: list<Modality>}>
     */
    protected function fetchRemoteEmbeddings(): iterable
    {
        $responseEmbeddings = $this->httpClient->request('GET', 'https://openrouter.ai/api/v1/embeddings/models');
        foreach ($responseEmbeddings->toArray()['data'] as $embedding) {
            yield $embedding['id'] => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ];
        }
    }
}
