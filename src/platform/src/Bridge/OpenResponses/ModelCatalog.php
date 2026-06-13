<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses;

use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Models need to be registered explicitly.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, tasks?: list<Task>, input?: list<Modality>, output?: list<Modality>, features?: list<Feature>}> $models
     */
    public function __construct(
        protected array $models = [],
    ) {
    }
}
