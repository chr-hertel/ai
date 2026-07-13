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

use Symfony\AI\Agent\Agent;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects the services tagged "ai.agent.context_processor" into the
 * "$contextProcessors" argument of every agent definition, honoring the
 * optional per-agent "agent" tag attribute and the "priority" ordering.
 */
class ProcessorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $contextProcessors = $container->findTaggedServiceIds('ai.agent.context_processor');

        foreach ($container->findTaggedServiceIds('ai.agent') as $serviceId => $tags) {
            $agentDefinition = $container->getDefinition($serviceId);

            // Only plain agents accept a $contextProcessors argument.
            if (Agent::class !== $agentDefinition->getClass()) {
                continue;
            }

            $agentContextProcessors = [];
            foreach ($contextProcessors as $processorId => $processorTags) {
                foreach ($processorTags as $tag) {
                    if ('interface' === ($tag['tagged_by'] ?? null) && \count($processorTags) > 1) {
                        continue;
                    }

                    $agent = $tag['agent'] ?? null;
                    if (null === $agent || $agent === $serviceId) {
                        $priority = $tag['priority'] ?? 0;
                        $agentContextProcessors[] = [$priority, new Reference($processorId)];
                    }
                }
            }

            $sortCb = static fn (array $a, array $b): int => $b[0] <=> $a[0];
            usort($agentContextProcessors, $sortCb);

            $agentDefinition->setArgument('$contextProcessors', array_column($agentContextProcessors, 1));
        }
    }
}
