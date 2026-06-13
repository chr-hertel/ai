<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere;

use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, tasks: list<Task>, input: list<Modality>, output: list<Modality>, features: list<Feature>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        // STATIC LIST START
        // This list is generated from external metadata. Run dev/update-model-catalogs.php to refresh it.
        $defaultModels = [
            'c4ai-aya-expanse-32b' => [
                'class' => Cohere::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'c4ai-aya-expanse-8b' => [
                'class' => Cohere::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'c4ai-aya-vision-32b' => [
                'class' => Cohere::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'c4ai-aya-vision-8b' => [
                'class' => Cohere::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'cohere-transcribe-03-2026' => [
                'class' => SpeechToText::class,
                'tasks' => [
                    Task::TRANSCRIPTION,
                ],
                'input' => [
                    Modality::AUDIO,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'command-a-03-2025' => [
                'class' => Cohere::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'command-a-reasoning-08-2025' => [
                'class' => Cohere::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::THINKING,
                ],
            ],
            'command-a-translate-08-2025' => [
                'class' => Cohere::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::STREAMING,
                ],
            ],
            'command-a-vision-07-2025' => [
                'class' => Cohere::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'command-r-08-2024' => [
                'class' => Cohere::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'command-r-plus-08-2024' => [
                'class' => Cohere::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'command-r7b-12-2024' => [
                'class' => Cohere::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'command-r7b-arabic-02-2025' => [
                'class' => Cohere::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'embed-english-light-v3.0' => [
                'class' => Embeddings::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
            'embed-english-v3.0' => [
                'class' => Embeddings::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
            'embed-multilingual-light-v3.0' => [
                'class' => Embeddings::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
            'embed-multilingual-v3.0' => [
                'class' => Embeddings::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
            'embed-v4.0' => [
                'class' => Embeddings::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
            'rerank-english-v3.0' => [
                'class' => Reranker::class,
                'tasks' => [
                    Task::RERANKING,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
            'rerank-multilingual-v3.0' => [
                'class' => Reranker::class,
                'tasks' => [
                    Task::RERANKING,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
            'rerank-v3.5' => [
                'class' => Reranker::class,
                'tasks' => [
                    Task::RERANKING,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
            'rerank-v4.0-fast' => [
                'class' => Reranker::class,
                'tasks' => [
                    Task::RERANKING,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
            'rerank-v4.0-pro' => [
                'class' => Reranker::class,
                'tasks' => [
                    Task::RERANKING,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
        ];
        // STATIC LIST END

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
