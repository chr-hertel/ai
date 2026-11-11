<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\DependencyInjection;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\DependencyInjection\McpGuardPass;
use Symfony\AI\McpBundle\DependencyInjection\McpPass;
use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\AI\McpBundle\Guard\GuardedRegistry;
use Symfony\AI\McpBundle\Guard\McpGuardInterface;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\AI\McpBundle\Security\IsGrantedGuard;
use Symfony\AI\McpBundle\Security\IsGrantedListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class McpGuardPassTest extends TestCase
{
    public function testKeepsThePlainRegistryWithoutGuards()
    {
        $container = $this->process();

        $this->assertEquals([new Reference('mcp.server.default.registry')], $this->setRegistryArguments($container, 'default'));
        $this->assertFalse($container->hasDefinition('mcp.server.default.guarded_registry'));
    }

    public function testPutsTaggedGuardsInFrontOfEachServer()
    {
        $container = $this->process(static function (ContainerBuilder $container): void {
            $container->register('app.everywhere', AllowAllGuard::class)->addTag('mcp.guard');
            $container->register('app.admin_only', AllowAllGuard::class)->addTag('mcp.guard', ['server' => 'admin']);
        });

        $this->assertEquals([new Reference('mcp.server.default.guarded_registry')], $this->setRegistryArguments($container, 'default'));
        $this->assertEquals([new Reference('app.everywhere')], $container->getDefinition('mcp.server.default.guarded_registry')->getArgument(1));

        $guardedRegistry = $container->getDefinition('mcp.server.admin.guarded_registry');
        $this->assertSame(GuardedRegistry::class, $guardedRegistry->getClass());
        $this->assertEquals(new Reference('mcp.server.admin.registry'), $guardedRegistry->getArgument(0));
        $this->assertEquals([new Reference('app.everywhere'), new Reference('app.admin_only')], $guardedRegistry->getArgument(1));
    }

    public function testChecksTheIsGrantedAttributesOfTheElements()
    {
        $container = $this->process(static function (ContainerBuilder $container): void {
            $container->register('security.authorization_checker');
            $container->register(GuardedElements::class, GuardedElements::class)
                ->addTag('mcp.tool', ['method' => 'publish'])
                ->addTag('mcp.prompt', ['method' => 'reviewPrompt'])
                ->addTag('mcp.resource', ['method' => 'secrets'])
                ->addTag('mcp.tool', ['method' => 'open']);
        });

        $guard = $container->getDefinition('mcp.server.default.is_granted_guard');
        $this->assertSame(IsGrantedGuard::class, $guard->getClass());
        $this->assertSame([
            'tool' => ['publish' => [GuardedElements::class, 'publish']],
            'prompt' => ['reviewPrompt' => [GuardedElements::class, 'reviewPrompt']],
            'resource' => ['config://secrets' => [GuardedElements::class, 'secrets']],
        ], $guard->getArgument(1));
        $this->assertEquals([new Reference('mcp.server.default.is_granted_guard')], $container->getDefinition('mcp.server.default.guarded_registry')->getArgument(1));

        $listener = $container->getDefinition('mcp.server.default.is_granted_listener');
        $this->assertSame(IsGrantedListener::class, $listener->getClass());
        $this->assertSame('mcp.server.default.controller::handle', $listener->getArgument(0));
        $this->assertEquals(new Reference('mcp.server.default.registry'), $listener->getArgument(1));
        $this->assertSame([['event' => KernelEvents::CONTROLLER]], $listener->getTag('kernel.event_listener'));
    }

    public function testIsGrantedRequiresSecurity()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('composer require symfony/security-bundle');

        $this->process(static function (ContainerBuilder $container): void {
            $container->register(GuardedElements::class, GuardedElements::class)->addTag('mcp.tool', ['method' => 'publish']);
        });
    }

    public function testRejectsAnIsGrantedSubject()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('has a subject');

        $this->process(static function (ContainerBuilder $container): void {
            $container->register('security.authorization_checker');
            $container->register(SubjectElements::class, SubjectElements::class)->addTag('mcp.tool', ['method' => 'edit']);
        });
    }

    private function process(?\Closure $configure = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'public');
        $container->setParameter('kernel.project_dir', '/path/to/project');

        (new McpBundle())->getContainerExtension()->load(['mcp' => ['servers' => [
            'default' => ['registry' => '*'],
            'admin' => ['registry' => '*'],
        ]]], $container);

        if (null !== $configure) {
            $configure($container);
        }

        (new McpPass())->process($container);
        (new McpGuardPass())->process($container);

        return $container;
    }

    /**
     * @return array<mixed>
     */
    private function setRegistryArguments(ContainerBuilder $container, string $server): array
    {
        foreach ($container->getDefinition('mcp.server.'.$server.'.builder')->getMethodCalls() as [$method, $arguments]) {
            if ('setRegistry' === $method) {
                return $arguments;
            }
        }

        $this->fail('The builder has no registry.');
    }
}

final class AllowAllGuard implements McpGuardInterface
{
    public function isGranted(Tool|Prompt|ResourceDefinition|ResourceTemplate $element): bool
    {
        return true;
    }
}

final class GuardedElements
{
    #[McpTool(name: 'publish')]
    #[IsGranted('ROLE_EDITOR')]
    public function publish(): string
    {
        return 'published';
    }

    /**
     * @return list<mixed>
     */
    #[McpPrompt]
    #[IsGranted('ROLE_REVIEWER')]
    public function reviewPrompt(): array
    {
        return [];
    }

    #[McpResource(uri: 'config://secrets', name: 'secrets')]
    #[IsGranted('ROLE_ADMIN')]
    public function secrets(): string
    {
        return '';
    }

    #[McpTool(name: 'open')]
    public function open(): string
    {
        return 'open';
    }
}

final class SubjectElements
{
    #[McpTool(name: 'edit')]
    #[IsGranted('EDIT', subject: 'post')]
    public function edit(string $post): string
    {
        return $post;
    }
}
