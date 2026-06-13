<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Bridge\Meta\Llama;
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
            'nova-micro' => [
                'class' => Nova::class,
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
            'nova-lite' => [
                'class' => Nova::class,
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
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'nova-pro' => [
                'class' => Nova::class,
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
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'nova-2-lite' => [
                'class' => Nova::class,
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
                    Feature::STRUCTURED_OUTPUT,
                ],
            ],
            'claude-3-haiku-20240307' => [
                'class' => Claude::class,
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
                ],
            ],
            'claude-3-sonnet-20240229' => [
                'class' => Claude::class,
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
                ],
            ],
            'claude-3-7-sonnet-20250219' => [
                'class' => Claude::class,
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
                ],
            ],
            'claude-sonnet-4-20250514' => [
                'class' => Claude::class,
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
                ],
            ],
            'claude-haiku-4-5-20251001' => [
                'class' => Claude::class,
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
            'claude-sonnet-4-5-20250929' => [
                'class' => Claude::class,
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
            'claude-opus-4-5-20251101' => [
                'class' => Claude::class,
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
            'claude-sonnet-4-6' => [
                'class' => Claude::class,
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
            'claude-opus-4-6' => [
                'class' => Claude::class,
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
            'claude-opus-4-7' => [
                'class' => Claude::class,
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
            'llama-3.2-1b-instruct' => [
                'class' => Llama::class,
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
            'llama-3.2-3b-instruct' => [
                'class' => Llama::class,
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
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
