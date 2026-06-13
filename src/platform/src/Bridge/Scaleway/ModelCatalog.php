<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway;

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
     * @param array<string, array{class: string, tasks: list<Task>, input: list<Modality>, output: list<Modality>, features: list<Feature>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'deepseek-r1-distill-llama-70b' => [
                'class' => Scaleway::class,
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
            'gemma-3-27b-it' => [
                'class' => Scaleway::class,
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
            'llama-3.1-8b-instruct' => [
                'class' => Scaleway::class,
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
            'llama-3.3-70b-instruct' => [
                'class' => Scaleway::class,
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
            'devstral-small-2505' => [
                'class' => Scaleway::class,
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
            'mistral-nemo-instruct-2407' => [
                'class' => Scaleway::class,
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
            'pixtral-12b-2409' => [
                'class' => Scaleway::class,
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
            'mistral-small-3.2-24b-instruct-2506' => [
                'class' => Scaleway::class,
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
            'gpt-oss-120b' => [
                'class' => ScalewayResponses::class,
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
            'qwen3-coder-30b-a3b-instruct' => [
                'class' => Scaleway::class,
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
            'qwen3-235b-a22b-instruct-2507' => [
                'class' => Scaleway::class,
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
            'qwen3.5-397b-a17b' => [
                'class' => Scaleway::class,
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
                    Feature::THINKING,
                ],
            ],
            'qwen3-embedding-8b' => [
                'class' => Embeddings::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'bge-multilingual-gemma2' => [
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
