<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\AiBundle\DependencyInjection\ProcessorCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ProcessorCompilerPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container
            ->register('agent1', Agent::class)
            ->addTag('ai.agent');
        $container
            ->register('agent2', Agent::class)
            ->addTag('ai.agent');
        $container
            ->register(DummyContextProcessor1::class, DummyContextProcessor1::class)
            ->addTag('ai.agent.context_processor', ['tagged_by' => 'interface']);
        $container
            ->register(DummyContextProcessor2::class, DummyContextProcessor2::class)
            ->addTag('ai.agent.context_processor', ['tagged_by' => 'interface']);
        $container
            ->register(DummyContextProcessor3::class, DummyContextProcessor3::class)
            ->addTag('ai.agent.context_processor', ['tagged_by' => 'interface']);
        $container
            ->register(DummyContextProcessor1::class, DummyContextProcessor1::class)
            ->addTag('ai.agent.context_processor', ['agent' => 'agent1', 'priority' => -100]);
        $container
            ->register(DummyContextProcessor2::class, DummyContextProcessor2::class)
            ->addTag('ai.agent.context_processor', ['agent' => 'agent2']);
        $container
            ->register(DummyContextProcessor3::class, DummyContextProcessor3::class)
            ->addTag('ai.agent.context_processor', ['priority' => 100]);

        (new ProcessorCompilerPass())->process($container);

        // agent1: global processor 3 (priority 100) then the agent-specific processor 1 (priority -100).
        $this->assertEquals(
            [
                new Reference(DummyContextProcessor3::class),
                new Reference(DummyContextProcessor1::class),
            ],
            $container->getDefinition('agent1')->getArgument('$contextProcessors')
        );
        // agent2: global processor 3 (priority 100) then the agent-specific processor 2 (priority 0).
        $this->assertEquals(
            [
                new Reference(DummyContextProcessor3::class),
                new Reference(DummyContextProcessor2::class),
            ],
            $container->getDefinition('agent2')->getArgument('$contextProcessors')
        );
    }

    public function testProcessSkipsNonAgentDefinitions()
    {
        $container = new ContainerBuilder();

        // Regular Agent service - should be processed.
        $container
            ->register('agent1', Agent::class)
            ->addTag('ai.agent');

        // A tagged service that is not an Agent - should be skipped.
        $container
            ->register('decorated_agent', DummyContextProcessor1::class)
            ->setArguments(['untouched'])
            ->addTag('ai.agent');

        $container
            ->register(DummyContextProcessor1::class, DummyContextProcessor1::class)
            ->addTag('ai.agent.context_processor');

        (new ProcessorCompilerPass())->process($container);

        $this->assertEquals(
            [new Reference(DummyContextProcessor1::class)],
            $container->getDefinition('agent1')->getArgument('$contextProcessors')
        );

        // The non-Agent definition keeps its original arguments.
        $this->assertSame('untouched', $container->getDefinition('decorated_agent')->getArgument(0));
    }
}

class DummyContextProcessor1 implements ContextProcessorInterface
{
    public static function supportedTypes(): array
    {
        return [];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
    }
}
class DummyContextProcessor2 implements ContextProcessorInterface
{
    public static function supportedTypes(): array
    {
        return [];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
    }
}
class DummyContextProcessor3 implements ContextProcessorInterface
{
    public static function supportedTypes(): array
    {
        return [];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
    }
}
