<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decart;

use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<string>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'lucy-dev-i2v' => [
                'class' => Decart::class,
                'tasks' => [
                    Task::VIDEO_GENERATION,
                ],
                'input' => [
                    Modality::IMAGE,
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::VIDEO,
                ],
            ],
            'lucy-pro-t2i' => [
                'class' => Decart::class,
                'tasks' => [
                    Task::IMAGE_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::IMAGE,
                ],
            ],
            'lucy-pro-t2v' => [
                'class' => Decart::class,
                'tasks' => [
                    Task::VIDEO_GENERATION,
                ],
                'input' => [
                    Modality::TEXT,
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::VIDEO,
                ],
            ],
            'lucy-pro-i2i' => [
                'class' => Decart::class,
                'tasks' => [
                    Task::IMAGE_GENERATION,
                ],
                'input' => [
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::IMAGE,
                ],
            ],
            'lucy-pro-i2v' => [
                'class' => Decart::class,
                'tasks' => [
                    Task::VIDEO_GENERATION,
                ],
                'input' => [
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::VIDEO,
                ],
            ],
            'lucy-pro-v2v' => [
                'class' => Decart::class,
                'tasks' => [
                    Task::VIDEO_GENERATION,
                ],
                'input' => [
                    Modality::VIDEO,
                ],
                'output' => [
                    Modality::VIDEO,
                ],
            ],
            'lucy-pro-flf2v' => [
                'class' => Decart::class,
                'tasks' => [
                    Task::VIDEO_GENERATION,
                ],
                'input' => [
                    Modality::IMAGE,
                ],
                'output' => [
                    Modality::VIDEO,
                ],
            ],
        ];

        $this->models = [
            ...$defaultModels,
            ...$additionalModels,
        ];
    }
}
