<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi;

use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\Model as EmbeddingsModel;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model as GeminiModel;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @see https://cloud.google.com/vertex-ai/generative-ai/docs/model-reference/inference for more details
 * @see https://cloud.google.com/vertex-ai/generative-ai/docs/model-reference/text-embeddings-api for various options
 *
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
            // Gemini models
            'gemini-3-pro-preview' => [
                'class' => GeminiModel::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::PDF,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'gemini-2.5-pro' => [
                'class' => GeminiModel::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::PDF,
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
            'gemini-3-flash-preview' => [
                'class' => GeminiModel::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
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
            'gemini-2.5-flash' => [
                'class' => GeminiModel::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::PDF,
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
            'gemini-2.5-flash-image' => [
                'class' => GeminiModel::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'features' => [
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'gemini-2.0-flash' => [
                'class' => GeminiModel::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            'gemini-3.1-flash-lite-preview' => [
                'class' => GeminiModel::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::VIDEO,
                    Modality::PDF,
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
            'gemini-2.5-flash-lite' => [
                'class' => GeminiModel::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::PDF,
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
            'gemini-2.0-flash-lite' => [
                'class' => GeminiModel::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::AUDIO,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::TOOL_CALLING,
                    Feature::STREAMING,
                ],
            ],
            // Embeddings models
            'gemini-embedding-001' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
            'text-embedding-005' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
            'text-multilingual-embedding-002' => [
                'class' => EmbeddingsModel::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'features' => [
                    Feature::MULTIPLE_INPUTS,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
