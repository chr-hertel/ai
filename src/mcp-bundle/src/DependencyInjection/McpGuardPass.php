<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\DependencyInjection;

use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\AI\McpBundle\Guard\GuardedRegistry;
use Symfony\AI\McpBundle\Security\IsGrantedGuard;
use Symfony\AI\McpBundle\Security\IsGrantedListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Puts the guards of each server in front of its registry: the services tagged "mcp.guard" and, when
 * elements carry #[IsGranted] attributes, the {@see IsGrantedGuard} checking them.
 *
 * A server without guards keeps its plain registry.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpGuardPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var array<string, array<string, array<string, array{class-string, string}>>> $isGranted */
        $isGranted = $container->hasParameter('mcp.servers.is_granted') ? $container->getParameter('mcp.servers.is_granted') : [];
        $container->getParameterBag()->remove('mcp.servers.is_granted');

        $guards = $container->findTaggedServiceIds('mcp.guard');

        foreach ($container->findTaggedServiceIds('mcp.server.builder') as $builderId => $builderTags) {
            $server = $builderTags[0]['server'];
            $references = [];

            foreach ($guards as $guardId => $tags) {
                if ($this->guardsServer($tags, $server)) {
                    $references[] = new Reference($guardId);
                }
            }

            $handlers = $isGranted[$server] ?? [];
            if ([] !== $handlers) {
                $references[] = $this->registerIsGrantedGuard($container, $server, $handlers);
            }

            if ([] === $references) {
                continue;
            }

            $registryId = \sprintf('mcp.server.%s.registry', $server);
            $guardedRegistryId = \sprintf('mcp.server.%s.guarded_registry', $server);
            $container->register($guardedRegistryId, GuardedRegistry::class)
                ->setArguments([new Reference($registryId), $references]);

            $builder = $container->getDefinition($builderId);
            $calls = $builder->getMethodCalls();
            foreach ($calls as $i => [$method]) {
                if ('setRegistry' === $method) {
                    $calls[$i][1] = [new Reference($guardedRegistryId)];
                }
            }
            $builder->setMethodCalls($calls);
        }
    }

    /**
     * @param list<array<string, mixed>> $tags
     */
    private function guardsServer(array $tags, string $server): bool
    {
        foreach ($tags as $tag) {
            if (!isset($tag['server']) || $tag['server'] === $server) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<string, array{class-string, string}>> $handlers
     */
    private function registerIsGrantedGuard(ContainerBuilder $container, string $server, array $handlers): Reference
    {
        if (!$container->has('security.authorization_checker')) {
            throw new LogicException(\sprintf('MCP elements of server "%s" carry #[IsGranted] attributes, but no authorization checker is available. Run "composer require symfony/security-bundle".', $server));
        }

        $guardId = \sprintf('mcp.server.%s.is_granted_guard', $server);
        $container->register($guardId, IsGrantedGuard::class)
            ->setArguments([new Reference('security.authorization_checker'), $handlers]);

        $controllerId = \sprintf('mcp.server.%s.controller', $server);
        if ($container->hasDefinition($controllerId)) {
            $container->register(\sprintf('mcp.server.%s.is_granted_listener', $server), IsGrantedListener::class)
                ->setArguments([
                    $controllerId.'::handle',
                    new Reference(\sprintf('mcp.server.%s.registry', $server)),
                    new Reference($guardId),
                ])
                ->addTag('kernel.event_listener', ['event' => KernelEvents::CONTROLLER]);
        }

        return new Reference($guardId);
    }
}
