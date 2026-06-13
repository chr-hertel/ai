<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras\Tests;

use Symfony\AI\Platform\Bridge\Cerebras\ModelCatalog;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'llama-4-scout-17b-16e-instruct' => ['llama-4-scout-17b-16e-instruct', Model::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'llama3.1-8b' => ['llama3.1-8b', Model::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'llama-3.3-70b' => ['llama-3.3-70b', Model::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'llama-4-maverick-17b-128e-instruct' => ['llama-4-maverick-17b-128e-instruct', Model::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'qwen-3-32b' => ['qwen-3-32b', Model::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'qwen-3-235b-a22b-instruct-2507' => ['qwen-3-235b-a22b-instruct-2507', Model::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'qwen-3-235b-a22b-thinking-2507' => ['qwen-3-235b-a22b-thinking-2507', Model::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'qwen-3-coder-480b' => ['qwen-3-coder-480b', Model::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-oss-120b' => ['gpt-oss-120b', Model::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'zai-glm-4.7' => ['zai-glm-4.7', Model::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
