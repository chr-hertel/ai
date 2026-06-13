<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Task;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog implements ModelCatalogInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getModel(string $modelName): ElevenLabs
    {
        $models = $this->getModels();

        if (!\array_key_exists($modelName, $models)) {
            throw new InvalidArgumentException(\sprintf('The model "%s" cannot be retrieved from the API.', $modelName));
        }

        $model = self::buildModel($modelName, $models[$modelName]);
        if ([] === $model->getTasks()) {
            throw new InvalidArgumentException(\sprintf('The model "%s" is not supported, please check the ElevenLabs API.', $modelName));
        }

        return $model;
    }

    public function getModels(): array
    {
        $response = $this->httpClient->request('GET', 'models');

        $models = $response->toArray();

        $profile = static fn (array $model): array => match (true) {
            $model['can_do_text_to_speech'] => [
                'tasks' => [Task::SPEECH_SYNTHESIS],
                'input' => [Modality::TEXT],
                'output' => [Modality::AUDIO],
            ],
            $model['can_do_voice_conversion'] => [
                'tasks' => [Task::TRANSCRIPTION],
                'input' => [Modality::AUDIO],
                'output' => [Modality::TEXT],
            ],
            default => [],
        };

        return array_combine(
            array_map(static fn (array $model): string => $model['model_id'], $models),
            array_map(static fn (array $model): array => [
                'class' => ElevenLabs::class,
            ] + $profile($model), $models),
        ) + [
            'scribe_v1' => [
                'class' => ElevenLabs::class,
                'tasks' => [Task::TRANSCRIPTION],
                'input' => [Modality::AUDIO],
                'output' => [Modality::TEXT],
            ],
            'scribe_v2' => [
                'class' => ElevenLabs::class,
                'tasks' => [Task::TRANSCRIPTION],
                'input' => [Modality::AUDIO],
                'output' => [Modality::TEXT],
            ],
        ];
    }

    /**
     * @param array{class: class-string, tasks?: list<Task>, input?: list<Modality>, output?: list<Modality>} $modelConfig
     */
    private static function buildModel(string $name, array $modelConfig): ElevenLabs
    {
        return new ElevenLabs(
            $name,
            $modelConfig['tasks'] ?? [],
            $modelConfig['input'] ?? [],
            $modelConfig['output'] ?? [],
        );
    }
}
