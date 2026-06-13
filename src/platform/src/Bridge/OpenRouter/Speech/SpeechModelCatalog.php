<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Speech;

use Symfony\AI\Platform\Bridge\OpenRouter\SpeechModel;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class SpeechModelCatalog extends AbstractModelCatalog
{
    /**
     * @var array<string, array{class: class-string, tasks: list<Task>, input: list<Modality>, output: list<Modality>, features: list<Feature>}>
     */
    protected array $models = [
        'canopylabs/orpheus-3b-0.1-ft' => [
            'class' => SpeechModel::class,
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
        'google/gemini-3.1-flash-tts-preview' => [
            'class' => SpeechModel::class,
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
        'hexgrad/kokoro-82m' => [
            'class' => SpeechModel::class,
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
        'mistralai/voxtral-mini-tts-2603' => [
            'class' => SpeechModel::class,
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
        'openai/gpt-4o-mini-tts-2025-12-15' => [
            'class' => SpeechModel::class,
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
        'sesame/csm-1b' => [
            'class' => SpeechModel::class,
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
        'zyphra/zonos-v0.1-hybrid' => [
            'class' => SpeechModel::class,
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
        'zyphra/zonos-v0.1-transformer' => [
            'class' => SpeechModel::class,
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
    ];
}
