<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Merges per-agent MCP client adapters into each toolbox's tool list.
 *
 * Toolboxes that received `mcp_servers` configuration are tagged with
 * `ai.toolbox.mcp_pending` carrying the adapter service ids. This pass resolves
 * those ids and prepends them to the toolbox's first constructor argument so
 * MCP adapters coexist with explicit services or with the global
 * `#[AsTool]`-tagged iterator.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpToolboxCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('ai.toolbox.mcp_pending') as $toolboxId => $tags) {
            $adapterIds = [];

            foreach ($tags as $tag) {
                foreach ($tag['adapters'] ?? [] as $adapterId) {
                    $adapterIds[] = $adapterId;
                }
            }

            if ([] === $adapterIds) {
                continue;
            }

            $definition = $container->getDefinition($toolboxId);
            $definition->clearTag('ai.toolbox.mcp_pending');

            $current = $definition->getArgument(0);
            $adapterRefs = array_map(static fn (string $id): Reference => new Reference($id), $adapterIds);

            if ($current instanceof TaggedIteratorArgument) {
                $tagged = $container->findTaggedServiceIds($current->getTag());
                $taggedRefs = [];

                foreach ($tagged as $id => $_) {
                    $taggedRefs[] = new Reference($id);
                }

                $merged = new IteratorArgument(array_merge($taggedRefs, $adapterRefs));
            } elseif (\is_array($current)) {
                $merged = array_merge($current, $adapterRefs);
            } else {
                $merged = $adapterRefs;
            }

            $definition->replaceArgument(0, $merged);
        }
    }
}
