<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LmStudio\Tests;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\LmStudio\ModelCatalog;
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
        yield 'gemma-3-4b-it-qat' => [
            'gemma-3-4b-it-qat',
            CompletionsModel::class,
            [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], [Feature::TOOL_CALLING, Feature::STREAMING],
        ];

        yield 'text-embedding-nomic-embed-text-v2-moe' => [
            'text-embedding-nomic-embed-text-v2-moe',
            EmbeddingsModel::class,
            [Task::EMBEDDING], [Modality::TEXT], [], [],
        ];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
