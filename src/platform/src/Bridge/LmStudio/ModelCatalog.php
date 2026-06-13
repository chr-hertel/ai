<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LmStudio;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
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
            'gemma-3-4b-it-qat' => [
                'class' => CompletionsModel::class,
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
            'text-embedding-nomic-embed-text-v2-moe' => [
                'class' => EmbeddingsModel::class,
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
