<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests;

use Symfony\AI\Platform\Bridge\Gemini\Embeddings;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\ModelCatalog;
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
        yield 'gemini-3.1-pro-preview' => ['gemini-3.1-pro-preview', Gemini::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::VIDEO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'gemini-3-flash-preview' => ['gemini-3-flash-preview', Gemini::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::PDF], [], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gemini-3-pro-preview' => ['gemini-3-pro-preview', Gemini::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::VIDEO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'gemini-3-pro-image-preview' => ['gemini-3-pro-image-preview', Gemini::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT, Modality::IMAGE], [Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'gemini-2.5-flash-image' => ['gemini-2.5-flash-image', Gemini::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT, Modality::IMAGE], [Feature::STRUCTURED_OUTPUT]];
        yield 'gemini-2.5-flash' => ['gemini-2.5-flash', Gemini::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::VIDEO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'gemini-2.5-pro' => ['gemini-2.5-pro', Gemini::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::VIDEO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'gemini-2.5-flash-lite-preview-09-2025' => ['gemini-2.5-flash-lite-preview-09-2025', Gemini::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::VIDEO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'gemini-2.5-flash-lite' => ['gemini-2.5-flash-lite', Gemini::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::VIDEO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'gemini-2.0-flash' => ['gemini-2.0-flash', Gemini::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::AUDIO, Modality::VIDEO, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gemini-2.5-flash-native-audio-preview-12-2025' => ['gemini-2.5-flash-native-audio-preview-12-2025', Gemini::class, [Task::TEXT_GENERATION, Task::SPEECH_SYNTHESIS], [Modality::TEXT, Modality::AUDIO, Modality::VIDEO], [Modality::AUDIO], []];
        yield 'gemini-2.5-flash-preview-tts' => ['gemini-2.5-flash-preview-tts', Gemini::class, [Task::TEXT_GENERATION, Task::SPEECH_SYNTHESIS], [Modality::TEXT], [Modality::AUDIO], []];
        yield 'gemini-2.5-pro-preview-tts' => ['gemini-2.5-pro-preview-tts', Gemini::class, [Task::TEXT_GENERATION, Task::SPEECH_SYNTHESIS], [Modality::TEXT], [Modality::AUDIO], []];
        yield 'gemini-embedding-001' => ['gemini-embedding-001', Embeddings::class, [Task::EMBEDDING], [Modality::TEXT], [], []];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
