<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsDev;

use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;

/**
 * Maps models.dev model metadata to a Symfony AI model profile (tasks, modalities, features).
 *
 * @phpstan-type CapabilitiesArray array{tasks: list<Task>, input: list<Modality>, output: list<Modality>, features: list<Feature>}
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class CapabilityMapper
{
    /**
     * @param array{
     *     id: string,
     *     name: string,
     *     family?: string,
     *     attachment: bool,
     *     reasoning: bool,
     *     tool_call: bool,
     *     structured_output?: bool,
     *     temperature: bool,
     *     modalities: array{input: list<string>, output: list<string>},
     *     cost: array<string, float>,
     *     limit: array<string, int>,
     *     status?: string,
     *     knowledge?: string,
     *     release_date?: string,
     *     last_updated?: string,
     *     open_weights?: bool,
     * } $modelData
     *
     * @return CapabilitiesArray
     */
    public static function map(array $modelData): array
    {
        if (self::isEmbeddingModel($modelData)) {
            return self::mapEmbeddingProfile($modelData);
        }

        return self::mapCompletionProfile($modelData);
    }

    /**
     * @param array{
     *     id: string,
     *     family?: string,
     *     tool_call: bool,
     *     attachment: bool,
     *     reasoning: bool,
     *     modalities: array{input: list<string>, output: list<string>},
     * } $modelData
     */
    public static function isEmbeddingModel(array $modelData): bool
    {
        $family = $modelData['family'] ?? '';
        if ('' !== $family && str_contains($family, 'embed')) {
            return true;
        }

        if (str_contains($modelData['id'], 'embed')) {
            return true;
        }

        return false;
    }

    /**
     * @param array{
     *     tool_call: bool,
     *     structured_output?: bool,
     *     reasoning: bool,
     *     modalities: array{input: list<string>, output: list<string>},
     * } $modelData
     *
     * @return CapabilitiesArray
     */
    private static function mapCompletionProfile(array $modelData): array
    {
        $tasks = [Task::TEXT_GENERATION];
        $input = [Modality::TEXT];
        $output = [Modality::TEXT];
        $features = [Feature::STREAMING];

        if ($modelData['tool_call']) {
            $features[] = Feature::TOOL_CALLING;
        }

        if ($modelData['structured_output'] ?? false) {
            $features[] = Feature::STRUCTURED_OUTPUT;
        }

        if ($modelData['reasoning']) {
            $features[] = Feature::THINKING;
        }

        $inputModalities = $modelData['modalities']['input'] ?? [];
        if (\in_array('image', $inputModalities, true)) {
            $input[] = Modality::IMAGE;
        }
        if (\in_array('pdf', $inputModalities, true)) {
            $input[] = Modality::PDF;
        }
        if (\in_array('audio', $inputModalities, true)) {
            $input[] = Modality::AUDIO;
        }

        $outputModalities = $modelData['modalities']['output'] ?? [];
        if (\in_array('image', $outputModalities, true)) {
            $tasks[] = Task::IMAGE_GENERATION;
            $output[] = Modality::IMAGE;
        }
        if (\in_array('audio', $outputModalities, true)) {
            $tasks[] = Task::SPEECH_SYNTHESIS;
            $output[] = Modality::AUDIO;
        }

        return [
            'tasks' => $tasks,
            'input' => $input,
            'output' => $output,
            'features' => $features,
        ];
    }

    /**
     * @param array<string, mixed> $modelData
     *
     * @return CapabilitiesArray
     */
    private static function mapEmbeddingProfile(array $modelData): array
    {
        return [
            'tasks' => [Task::EMBEDDING],
            'input' => [Modality::TEXT],
            'output' => [],
            'features' => [],
        ];
    }
}
