<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\AI\Platform\Task;

/**
 * Add OpenRouter specific features to the model catalogues.
 *
 * Routers:
 * - "openrouter/auto" -> https://openrouter.ai/docs/guides/routing/routers/auto-router
 * - "openrouter/bodybuilder" -> https://openrouter.ai/docs/guides/routing/routers/body-builder
 * - "openrouter/free" -> https://openrouter.ai/openrouter/free & https://openrouter.ai/announcements/february-release-spotlight
 * - "@preset/" -> https://openrouter.ai/docs/guides/features/presets
 *
 * Provider selection modification
 * - ":nitro" -> https://openrouter.ai/docs/guides/routing/provider-selection#nitro-shortcut
 * - ":floor" -> https://openrouter.ai/docs/guides/routing/provider-selection#floor-price-shortcut
 *
 * Model variants:
 * - ":free" -> https://openrouter.ai/docs/guides/routing/model-variants/free
 * - ":extended" -> https://openrouter.ai/docs/guides/routing/model-variants/extended
 * - ":exacto" -> https://openrouter.ai/docs/guides/routing/model-variants/exacto
 * - ":thinking" -> https://openrouter.ai/docs/guides/routing/model-variants/thinking
 * - ":online" -> https://openrouter.ai/docs/guides/routing/model-variants/online
 *
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
abstract class AbstractOpenRouterModelCatalog extends AbstractModelCatalog
{
    public function __construct()
    {
        $profile = [
            'tasks' => Task::cases(),
            'input' => Modality::cases(),
            'output' => Modality::cases(),
            'features' => Feature::cases(),
        ];

        $this->models = [
            'openrouter/auto' => ['class' => CompletionsModel::class] + $profile,
            'openrouter/bodybuilder' => ['class' => CompletionsModel::class] + $profile,
            'openrouter/free' => ['class' => CompletionsModel::class] + $profile,
            '@preset' => ['class' => CompletionsModel::class] + $profile,
        ];
    }

    protected function parseModelName(string $modelName): array
    {
        if (str_starts_with($modelName, '@preset')) {
            return [
                'name' => $modelName,
                'catalogKey' => '@preset',
                'options' => [],
            ];
        }

        return parent::parseModelName($modelName);
    }
}
