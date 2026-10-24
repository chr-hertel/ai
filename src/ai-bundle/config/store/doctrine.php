<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Configurator;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

return (new ArrayNodeDefinition('doctrine'))
    ->info('Keeps the vectors in the #[Embedding] field of a Doctrine entity, and returns entities when queried.')
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('entity')
                ->info('FQCN of the entity with an #[Embedding] field.')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
            ->enumNode('distance')
                ->info('Distance metric to use for vector similarity search.')
                ->values(['cosine', 'euclidean', 'inner_product'])
                ->defaultValue('cosine')
            ->end()
            ->stringNode('entity_manager')
                ->info('Service id of the entity manager owning the entity.')
                ->defaultValue('doctrine.orm.entity_manager')
            ->end()
        ->end()
    ->end();
