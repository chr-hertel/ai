<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex;

use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\AI\Platform\Task;

/**
 * Default model catalog for the Codex CLI bridge.
 *
 * @see https://developers.openai.com/codex/models
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, tasks?: list<Task>, input?: list<Modality>, output?: list<Modality>, features?: list<Feature>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $profile = [
            'tasks' => [Task::TEXT_GENERATION],
            'input' => [Modality::TEXT],
            'output' => [Modality::TEXT],
            'features' => [Feature::STREAMING],
        ];

        $defaultModels = [
            'gpt-5.4' => ['class' => Codex::class] + $profile,
            'gpt-5.4-mini' => ['class' => Codex::class] + $profile,
            'gpt-5.3-codex' => ['class' => Codex::class] + $profile,
            'gpt-5.3-codex-spark' => ['class' => Codex::class] + $profile,
            'gpt-5.2' => ['class' => Codex::class] + $profile,
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
