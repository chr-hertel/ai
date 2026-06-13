<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cartesia;

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
            'sonic-3' => [
                'class' => Cartesia::class,
                'tasks' => [
                    Task::SPEECH_SYNTHESIS,
                ],
                'input' => [
                    Modality::TEXT,
                ],
                'output' => [
                    Modality::AUDIO,
                ],
            ],
            'ink-whisper' => [
                'class' => Cartesia::class,
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
        ];

        $this->models = [
            ...$defaultModels,
            ...$additionalModels,
        ];
    }
}
