<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

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
        // STATIC LIST START
        // This list is generated from external metadata. Run dev/update-model-catalogs.php to refresh it.
        $defaultModels = [
            'gemini-2.0-flash' => [
                'class' => Gemini::class,
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
                ],
            ],
            'gemini-2.0-flash-lite' => [
                'class' => Gemini::class,
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
            'gemini-2.5-flash' => [
                'class' => Gemini::class,
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
            'gemini-2.5-flash-image' => [
                'class' => Gemini::class,
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
            'gemini-2.5-flash-lite' => [
                'class' => Gemini::class,
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
            'gemini-2.5-flash-lite-preview-09-2025' => [
                'class' => Gemini::class,
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
            'gemini-2.5-flash-native-audio-preview-12-2025' => [
                'class' => Gemini::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                    Task::SPEECH_SYNTHESIS,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::AUDIO,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::AUDIO,
                ],
            ],
            'gemini-2.5-flash-preview-tts' => [
                'class' => Gemini::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                    Task::SPEECH_SYNTHESIS,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::AUDIO,
                ],
            ],
            'gemini-2.5-pro' => [
                'class' => Gemini::class,
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
            'gemini-2.5-pro-preview-tts' => [
                'class' => Gemini::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                    Task::SPEECH_SYNTHESIS,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::AUDIO,
                ],
            ],
            'gemini-3-flash-preview' => [
                'class' => Gemini::class,
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
            'gemini-3-pro-image-preview' => [
                'class' => Gemini::class,
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
                    Feature::THINKING,
                ],
            ],
            'gemini-3-pro-preview' => [
                'class' => Gemini::class,
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
            'gemini-3.1-flash-image-preview' => [
                'class' => Gemini::class,
                'tasks' => [
                    Task::TEXT_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                    Modality::PDF,
                ],
                'output' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'features' => [
                    Feature::STREAMING,
                    Feature::THINKING,
                ],
            ],
            'gemini-3.1-flash-lite' => [
                'class' => Gemini::class,
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
                    Feature::THINKING,
                ],
            ],
            'gemini-3.1-flash-lite-preview' => [
                'class' => Gemini::class,
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
                    Feature::THINKING,
                ],
            ],
            'gemini-3.1-pro-preview' => [
                'class' => Gemini::class,
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
            'gemini-3.1-pro-preview-customtools' => [
                'class' => Gemini::class,
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
                    Feature::THINKING,
                ],
            ],
            'gemini-3.5-flash' => [
                'class' => Gemini::class,
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
                    Feature::THINKING,
                ],
            ],
            'gemini-embedding-001' => [
                'class' => Embeddings::class,
                'tasks' => [
                    Task::EMBEDDING,
                ],
                'input' => [
                    Modality::TEXT,
                ],
            ],
            'gemini-flash-latest' => [
                'class' => Gemini::class,
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
                    Feature::THINKING,
                ],
            ],
            'gemini-flash-lite-latest' => [
                'class' => Gemini::class,
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
                    Feature::THINKING,
                ],
            ],
            'gemma-4-26b-a4b-it' => [
                'class' => Gemini::class,
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
                    Feature::THINKING,
                ],
            ],
            'gemma-4-31b-it' => [
                'class' => Gemini::class,
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
                    Feature::THINKING,
                ],
            ],
        ];
        // STATIC LIST END

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
