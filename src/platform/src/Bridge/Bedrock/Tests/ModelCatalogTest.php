<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\ModelCatalog;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Bridge\Meta\Llama;
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
        yield 'nova-micro' => ['nova-micro', Nova::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'nova-lite' => ['nova-lite', Nova::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STRUCTURED_OUTPUT]];
        yield 'nova-pro' => ['nova-pro', Nova::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STRUCTURED_OUTPUT]];
        yield 'nova-2-lite' => ['nova-2-lite', Nova::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STRUCTURED_OUTPUT]];
        yield 'claude-3-haiku-20240307' => ['claude-3-haiku-20240307', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];
        yield 'claude-3-sonnet-20240229' => ['claude-3-sonnet-20240229', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];
        yield 'claude-3-7-sonnet-20250219' => ['claude-3-7-sonnet-20250219', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];
        yield 'claude-sonnet-4-20250514' => ['claude-sonnet-4-20250514', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING]];
        yield 'claude-haiku-4-5-20251001' => ['claude-haiku-4-5-20251001', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'claude-sonnet-4-5-20250929' => ['claude-sonnet-4-5-20250929', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'claude-opus-4-5-20251101' => ['claude-opus-4-5-20251101', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'claude-sonnet-4-6' => ['claude-sonnet-4-6', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'claude-opus-4-6' => ['claude-opus-4-6', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'claude-opus-4-7' => ['claude-opus-4-7', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'llama-3.2-1b-instruct' => ['llama-3.2-1b-instruct', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3.2-3b-instruct' => ['llama-3.2-3b-instruct', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
