<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity\Tests;

use Symfony\AI\Platform\Bridge\Perplexity\ModelCatalog;
use Symfony\AI\Platform\Bridge\Perplexity\Perplexity;
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
        yield 'sonar' => ['sonar', Perplexity::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'sonar-pro' => ['sonar-pro', Perplexity::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'sonar-reasoning' => ['sonar-reasoning', Perplexity::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'sonar-reasoning-pro' => ['sonar-reasoning-pro', Perplexity::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::IMAGE, Modality::PDF], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
        yield 'sonar-deep-research' => ['sonar-deep-research', Perplexity::class, [Task::TEXT_GENERATION], [Modality::TEXT, Modality::PDF], [Modality::TEXT], [Feature::STREAMING, Feature::STRUCTURED_OUTPUT]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
