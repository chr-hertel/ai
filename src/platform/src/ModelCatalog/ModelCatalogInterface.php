<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\ModelCatalog;

use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelRequirements;
use Symfony\AI\Platform\Task;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface ModelCatalogInterface
{
    /**
     * @param non-empty-string $modelName
     */
    public function getModel(string $modelName): Model;

    /**
     * @return array<string, array{class: string, tasks?: list<Task>, input?: list<Modality>, output?: list<Modality>, features?: list<Feature>}>
     */
    public function getModels(): array;

    /**
     * Returns all catalog models that satisfy the given requirements, keyed by model name.
     *
     * @return array<string, Model>
     */
    public function findMatching(ModelRequirements $requirements): array;
}
