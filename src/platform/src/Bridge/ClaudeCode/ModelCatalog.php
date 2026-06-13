<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode;

use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, tasks: list<Task>, input: list<Modality>, output: list<Modality>, features: list<Feature>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'opus' => [
                'class' => ClaudeCode::class,
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
            'sonnet' => [
                'class' => ClaudeCode::class,
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
            'haiku' => [
                'class' => ClaudeCode::class,
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
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
