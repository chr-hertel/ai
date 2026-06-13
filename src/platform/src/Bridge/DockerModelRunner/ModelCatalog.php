<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DockerModelRunner;

use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<string>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            // Completions models
            'ai/gemma3n' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/gemma3' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/qwen2.5' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/qwen3' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/qwen3-coder' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/llama3.1' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/llama3.2' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/llama3.3' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/mistral' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/mistral-nemo' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/phi4' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/deepseek-r1-distill-llama' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/seed-oss' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/gpt-oss' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/smollm2' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            'ai/smollm3' => [
                'class' => Completions::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::TEXT,
                ],
            ],
            // Embeddings models
            'ai/nomic-embed-text-v1.5' => [
                'class' => Embeddings::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'ai/mxbai-embed-large' => [
                'class' => Embeddings::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'ai/embeddinggemma' => [
                'class' => Embeddings::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'ai/granite-embedding-multilingual' => [
                'class' => Embeddings::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
