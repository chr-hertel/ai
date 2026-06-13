<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Albert\Tests;

use Symfony\AI\Platform\Bridge\Albert\ModelCatalog;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
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
        yield 'openweight-small' => ['openweight-small', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING]];
        yield 'openweight-medium' => ['openweight-medium', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING]];
        yield 'openweight-large' => ['openweight-large', CompletionsModel::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING]];
        yield 'openweight-embeddings' => ['openweight-embeddings', EmbeddingsModel::class, [Task::EMBEDDING], [Modality::TEXT], [], []];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
