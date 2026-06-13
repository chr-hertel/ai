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

use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Task;

/**
 * Merges multiple model catalogs into a single catalog.
 *
 * Iterates through all registered catalogs and returns the first match.
 * This is used by Platform to provide a unified view of all models
 * available across multiple providers.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CompositeModelCatalog implements ModelCatalogInterface
{
    /**
     * @var array<string, array{class: string, tasks?: list<Task>, input?: list<Modality>, output?: list<Modality>, features?: list<Feature>}>|null
     */
    private ?array $mergedModels = null;

    /**
     * @param iterable<ModelCatalogInterface> $catalogs
     */
    public function __construct(
        private readonly iterable $catalogs,
    ) {
    }

    public function getModel(string $modelName): Model
    {
        foreach ($this->catalogs as $catalog) {
            try {
                return $catalog->getModel($modelName);
            } catch (ModelNotFoundException) {
                continue;
            }
        }

        throw new ModelNotFoundException(\sprintf('Model "%s" not found in any registered catalog.', $modelName));
    }

    /**
     * @return array<string, array{class: string, tasks?: list<Task>, input?: list<Modality>, output?: list<Modality>, features?: list<Feature>}>
     */
    public function getModels(): array
    {
        if (null !== $this->mergedModels) {
            return $this->mergedModels;
        }

        $merged = [];
        foreach ($this->catalogs as $catalog) {
            $merged += $catalog->getModels();
        }

        return $this->mergedModels = $merged;
    }
}
