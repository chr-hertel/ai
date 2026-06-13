<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex\Tests;

use Symfony\AI\Platform\Bridge\Codex\Codex;
use Symfony\AI\Platform\Bridge\Codex\ModelCatalog;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        $capabilities = [[Task::TEXT_GENERATION], [Modality::TEXT], [Modality::TEXT], [Feature::STREAMING]];

        yield 'gpt-5.4' => ['gpt-5.4', Codex::class, ...$capabilities];
        yield 'gpt-5.4-mini' => ['gpt-5.4-mini', Codex::class, ...$capabilities];
        yield 'gpt-5.3-codex' => ['gpt-5.3-codex', Codex::class, ...$capabilities];
        yield 'gpt-5.3-codex-spark' => ['gpt-5.3-codex-spark', Codex::class, ...$capabilities];
        yield 'gpt-5.2' => ['gpt-5.2', Codex::class, ...$capabilities];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
