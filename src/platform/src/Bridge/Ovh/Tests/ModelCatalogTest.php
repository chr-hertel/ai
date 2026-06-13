<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\Ovh\ModelCatalog;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        // Completions models
        yield 'Qwen3Guard-Gen-8B' => ['Qwen3Guard-Gen-8B', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING]];
        yield 'Qwen3Guard-Gen-0.6B' => ['Qwen3Guard-Gen-0.6B', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING]];
        yield 'Qwen3-Coder-30B-A3B-Instruct' => ['Qwen3-Coder-30B-A3B-Instruct', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-oss-20b' => ['gpt-oss-20b', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-oss-120b' => ['gpt-oss-120b', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'Qwen3-32B' => ['Qwen3-32B', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'Mistral-Small-3.2-24B-Instruct-2506' => ['Mistral-Small-3.2-24B-Instruct-2506', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'Qwen2.5-VL-72B-Instruct' => ['Qwen2.5-VL-72B-Instruct', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'Llama-3.1-8B-Instruct' => ['Llama-3.1-8B-Instruct', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'Mistral-7B-Instruct-v0.3' => ['Mistral-7B-Instruct-v0.3', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'Meta-Llama-3_3-70B-Instruct' => ['Meta-Llama-3_3-70B-Instruct', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'Mistral-Nemo-Instruct-2407' => ['Mistral-Nemo-Instruct-2407', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];

        // Embeddings models
        yield 'bge-multilingual-gemma2' => ['bge-multilingual-gemma2', EmbeddingsModel::class, [Task::EMBEDDING], [Modality::TEXT], [], []];
        yield 'bge-m3' => ['bge-m3', EmbeddingsModel::class, [Task::EMBEDDING], [Modality::TEXT], [], []];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
