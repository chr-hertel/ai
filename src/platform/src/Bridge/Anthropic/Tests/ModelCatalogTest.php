<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\ModelCatalog;
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
        yield 'claude-sonnet-4-20250514' => ['claude-sonnet-4-20250514', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::THINKING]];
        yield 'claude-sonnet-4-0' => ['claude-sonnet-4-0', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::THINKING]];
        yield 'claude-opus-4-20250514' => ['claude-opus-4-20250514', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::THINKING]];
        yield 'claude-opus-4-0' => ['claude-opus-4-0', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::THINKING]];
        yield 'claude-opus-4-1' => ['claude-opus-4-1', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'claude-opus-4-1-20250805' => ['claude-opus-4-1-20250805', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'claude-sonnet-4-5-20250929' => ['claude-sonnet-4-5-20250929', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'claude-haiku-4-5-20251001' => ['claude-haiku-4-5-20251001', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'claude-haiku-4-5' => ['claude-haiku-4-5', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'claude-opus-4-5-20251101' => ['claude-opus-4-5-20251101', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'claude-opus-4-6' => ['claude-opus-4-6', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'claude-sonnet-4-6' => ['claude-sonnet-4-6', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'claude-opus-4-7' => ['claude-opus-4-7', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'claude-opus-4-8' => ['claude-opus-4-8', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
        yield 'claude-fable-5' => ['claude-fable-5', Claude::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING, Feature::STRUCTURED_OUTPUT, Feature::THINKING]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
