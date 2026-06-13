<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Meta\Tests;

use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Bridge\Meta\ModelCatalog;
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
        yield 'llama-3.3-70B-Instruct' => ['llama-3.3-70B-Instruct', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3.2-90b-vision-instruct' => ['llama-3.2-90b-vision-instruct', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], []];
        yield 'llama-3.2-11b-vision-instruct' => ['llama-3.2-11b-vision-instruct', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE], [Modality::TEXT], []];
        yield 'llama-3.2-3b' => ['llama-3.2-3b', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3.2-3b-instruct' => ['llama-3.2-3b-instruct', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3.2-1b' => ['llama-3.2-1b', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3.2-1b-instruct' => ['llama-3.2-1b-instruct', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3.1-405b-instruct' => ['llama-3.1-405b-instruct', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3.1-70b' => ['llama-3.1-70b', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3-70b-instruct' => ['llama-3-70b-instruct', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3.1-8b' => ['llama-3.1-8b', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3.1-8b-instruct' => ['llama-3.1-8b-instruct', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3-70b' => ['llama-3-70b', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3-8b-instruct' => ['llama-3-8b-instruct', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
        yield 'llama-3-8b' => ['llama-3-8b', Llama::class, [Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], []];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
