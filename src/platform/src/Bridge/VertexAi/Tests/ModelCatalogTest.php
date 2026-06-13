<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests;

use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\Model as EmbeddingsModel;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model as GeminiModel;
use Symfony\AI\Platform\Bridge\VertexAi\ModelCatalog;
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
        // Gemini models
        yield 'gemini-2.5-pro' => ['gemini-2.5-pro', GeminiModel::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gemini-3-flash-preview' => ['gemini-3-flash-preview', GeminiModel::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::VIDEO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'gemini-2.5-flash' => ['gemini-2.5-flash', GeminiModel::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gemini-2.5-flash-image' => ['gemini-2.5-flash-image', GeminiModel::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT, Modality::IMAGE], [Feature::STRUCTURED_OUTPUT]];
        yield 'gemini-2.0-flash' => ['gemini-2.0-flash', GeminiModel::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];
        yield 'gemini-3.1-flash-lite-preview' => ['gemini-3.1-flash-lite-preview', GeminiModel::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::VIDEO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'gemini-2.5-flash-lite' => ['gemini-2.5-flash-lite', GeminiModel::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gemini-2.0-flash-lite' => ['gemini-2.0-flash-lite', GeminiModel::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];

        // Embeddings models
        yield 'gemini-embedding-001' => ['gemini-embedding-001', EmbeddingsModel::class, [Task::EMBEDDING], [Modality::TEXT], [], [Feature::MULTIPLE_INPUTS]];
        yield 'text-embedding-005' => ['text-embedding-005', EmbeddingsModel::class, [Task::EMBEDDING], [Modality::TEXT], [], [Feature::MULTIPLE_INPUTS]];
        yield 'text-multilingual-embedding-002' => ['text-multilingual-embedding-002', EmbeddingsModel::class, [Task::EMBEDDING], [Modality::TEXT], [], [Feature::MULTIPLE_INPUTS]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
