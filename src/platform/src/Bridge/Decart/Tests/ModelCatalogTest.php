<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decart\Tests;

use Symfony\AI\Platform\Bridge\Decart\Decart;
use Symfony\AI\Platform\Bridge\Decart\ModelCatalog;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'lucy-dev-i2v' => ['lucy-dev-i2v', Decart::class, [Task::VIDEO_GENERATION], [Modality::IMAGE, Modality::VIDEO], [Modality::VIDEO], []];
        yield 'lucy-pro-t2i' => ['lucy-pro-t2i', Decart::class, [Task::IMAGE_GENERATION], [Modality::TEXT], [Modality::IMAGE], []];
        yield 'lucy-pro-t2v' => ['lucy-pro-t2v', Decart::class, [Task::VIDEO_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::VIDEO], []];
        yield 'lucy-pro-i2i' => ['lucy-pro-i2i', Decart::class, [Task::IMAGE_GENERATION], [Modality::IMAGE], [Modality::IMAGE], []];
        yield 'lucy-pro-i2v' => ['lucy-pro-i2v', Decart::class, [Task::VIDEO_GENERATION], [Modality::IMAGE], [Modality::VIDEO], []];
        yield 'lucy-pro-v2v' => ['lucy-pro-v2v', Decart::class, [Task::VIDEO_GENERATION], [Modality::VIDEO], [Modality::VIDEO], []];
        yield 'lucy-pro-flf2v' => ['lucy-pro-flf2v', Decart::class, [Task::VIDEO_GENERATION], [Modality::IMAGE], [Modality::VIDEO], []];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
