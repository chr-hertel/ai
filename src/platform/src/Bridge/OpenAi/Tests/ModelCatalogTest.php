<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests;

use Symfony\AI\Platform\Bridge\OpenAi\DallE;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\ModelCatalog;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
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
        // GPT models
        yield 'gpt-3.5-turbo' => ['gpt-3.5-turbo', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];
        yield 'gpt-3.5-turbo-instruct' => ['gpt-3.5-turbo-instruct', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];
        yield 'gpt-4' => ['gpt-4', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];
        yield 'gpt-4-turbo' => ['gpt-4-turbo', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];
        yield 'gpt-4o' => ['gpt-4o', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-4o-mini' => ['gpt-4o-mini', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-4o-audio-preview' => ['gpt-4o-audio-preview', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'o3' => ['o3', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];
        yield 'o3-mini' => ['o3-mini', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'o3-mini-high' => ['o3-mini-high', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];
        yield 'gpt-4.5-preview' => ['gpt-4.5-preview', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-4.1' => ['gpt-4.1', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-4.1-mini' => ['gpt-4.1-mini', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-4.1-nano' => ['gpt-4.1-nano', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-5' => ['gpt-5', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-5-chat-latest' => ['gpt-5-chat-latest', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::STREAMING]];
        yield 'gpt-5-mini' => ['gpt-5-mini', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-5-nano' => ['gpt-5-nano', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-5.5' => ['gpt-5.5', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'gpt-5.5-pro' => ['gpt-5.5-pro', Gpt::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STRUCTURED_OUTPUT]];

        // Embedding models
        yield 'text-embedding-ada-002' => ['text-embedding-ada-002', Embeddings::class, [Task::EMBEDDING], [Modality::TEXT], [], []];
        yield 'text-embedding-3-large' => ['text-embedding-3-large', Embeddings::class, [Task::EMBEDDING], [Modality::TEXT], [], []];
        yield 'text-embedding-3-small' => ['text-embedding-3-small', Embeddings::class, [Task::EMBEDDING], [Modality::TEXT], [], []];

        // Text-to-speech models
        yield 'tts-1' => ['tts-1', TextToSpeech::class, [], [Modality::TEXT], [Modality::AUDIO], []];
        yield 'tts-1-hd' => ['tts-1-hd', TextToSpeech::class, [], [Modality::TEXT], [Modality::AUDIO], []];
        yield 'gpt-4o-mini-tts' => ['gpt-4o-mini-tts', TextToSpeech::class, [], [Modality::TEXT], [Modality::AUDIO], []];

        // Whisper models
        yield 'whisper-1' => ['whisper-1', Whisper::class, [], [Modality::AUDIO], [Modality::TEXT], []];

        // DALL-E models
        yield 'dall-e-2' => ['dall-e-2', DallE::class, [], [Modality::TEXT], [Modality::IMAGE], []];
        yield 'dall-e-3' => ['dall-e-3', DallE::class, [], [Modality::TEXT], [Modality::IMAGE], []];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
