<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway\Tests;

use Symfony\AI\Platform\Bridge\Scaleway\Embeddings;
use Symfony\AI\Platform\Bridge\Scaleway\ModelCatalog;
use Symfony\AI\Platform\Bridge\Scaleway\Scaleway;
use Symfony\AI\Platform\Bridge\Scaleway\ScalewayResponses;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        // Scaleway models
        yield 'deepseek-r1-distill-llama-70b' => ['deepseek-r1-distill-llama-70b', Scaleway::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gemma-3-27b-it' => ['gemma-3-27b-it', Scaleway::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'llama-3.1-8b-instruct' => ['llama-3.1-8b-instruct', Scaleway::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'llama-3.3-70b-instruct' => ['llama-3.3-70b-instruct', Scaleway::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'devstral-small-2505' => ['devstral-small-2505', Scaleway::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'mistral-nemo-instruct-2407' => ['mistral-nemo-instruct-2407', Scaleway::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'pixtral-12b-2409' => ['pixtral-12b-2409', Scaleway::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'mistral-small-3.2-24b-instruct-2506' => ['mistral-small-3.2-24b-instruct-2506', Scaleway::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-oss-120b' => ['gpt-oss-120b', ScalewayResponses::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'qwen3-coder-30b-a3b-instruct' => ['qwen3-coder-30b-a3b-instruct', Scaleway::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'qwen3-235b-a22b-instruct-2507' => ['qwen3-235b-a22b-instruct-2507', Scaleway::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];

        // Embedding models
        yield 'bge-multilingual-gemma2' => ['bge-multilingual-gemma2', Embeddings::class, [Task::EMBEDDING], [Modality::TEXT], [], []];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
