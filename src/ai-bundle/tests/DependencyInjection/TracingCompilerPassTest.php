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
use Symfony\AI\AiBundle\DependencyInjection\TracingCompilerPass;
use Symfony\AI\Telemetry\Agent\TracingAgent;
use Symfony\AI\Telemetry\Platform\TracingPlatform;
use Symfony\AI\Telemetry\Store\TracingRetriever;
use Symfony\AI\Telemetry\Toolbox\TracingToolbox;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class TracingCompilerPassTest extends TestCase
{
    public function testProcessDecoratesTaggedServices()
    {
        $container = $this->container(['platform', 'agent', 'toolbox', 'retriever'], true);

        (new TracingCompilerPass())->process($container);

        $platform = $container->getDefinition('ai.platform.openai.tracing');
        $this->assertSame(TracingPlatform::class, $platform->getClass());
        $this->assertSame(['ai.platform.openai', null, -768], $platform->getDecoratedService());
        $this->assertEquals([new Reference('.inner'), new Reference('ai.tracing.tracer'), 'openai', true, null, null], $platform->getArguments());

        $agent = $container->getDefinition('ai.agent.support.tracing');
        $this->assertSame(TracingAgent::class, $agent->getClass());
        $this->assertSame(['ai.agent.support', null, -768], $agent->getDecoratedService());
        $this->assertEquals([new Reference('.inner'), new Reference('ai.tracing.tracer'), true, null, null], $agent->getArguments());

        $toolbox = $container->getDefinition('ai.toolbox.support.tracing');
        $this->assertSame(TracingToolbox::class, $toolbox->getClass());
        $this->assertSame(['ai.toolbox.support', null, -768], $toolbox->getDecoratedService());
        $this->assertEquals([new Reference('.inner'), new Reference('ai.tracing.tracer'), true, null], $toolbox->getArguments());

        $retriever = $container->getDefinition('ai.retriever.blog.tracing');
        $this->assertSame(TracingRetriever::class, $retriever->getClass());
        $this->assertSame(['ai.retriever.blog', null, -768], $retriever->getDecoratedService());
        $this->assertEquals([new Reference('.inner'), new Reference('ai.tracing.tracer'), 'blog'], $retriever->getArguments());
    }

    public function testProcessPassesTheUserIdResolverToPlatformsAndAgents()
    {
        $container = $this->container(['platform', 'agent', 'toolbox'], false, true);

        (new TracingCompilerPass())->process($container);

        $resolver = new Reference('ai.tracing.user_id_resolver');
        $this->assertEquals($resolver, $container->getDefinition('ai.platform.openai.tracing')->getArgument(4));
        $this->assertEquals($resolver, $container->getDefinition('ai.agent.support.tracing')->getArgument(3));
        $this->assertSame(4, \count($container->getDefinition('ai.toolbox.support.tracing')->getArguments()), 'Tool spans inherit the user from the agent span');
    }

    public function testProcessPassesTheContentRedactorToAllDecorators()
    {
        $container = $this->container(['platform', 'agent', 'toolbox'], true, redactor: 'app.pii_redactor');

        (new TracingCompilerPass())->process($container);

        $redactor = new Reference('app.pii_redactor');
        $this->assertEquals($redactor, $container->getDefinition('ai.platform.openai.tracing')->getArgument(5));
        $this->assertEquals($redactor, $container->getDefinition('ai.agent.support.tracing')->getArgument(4));
        $this->assertEquals($redactor, $container->getDefinition('ai.toolbox.support.tracing')->getArgument(3));
    }

    public function testProcessOnlyDecoratesInstrumentedServices()
    {
        $container = $this->container(['agent'], false);

        (new TracingCompilerPass())->process($container);

        $this->assertTrue($container->hasDefinition('ai.agent.support.tracing'));
        $this->assertFalse($container->hasDefinition('ai.platform.openai.tracing'));
        $this->assertFalse($container->hasDefinition('ai.toolbox.support.tracing'));
        $this->assertFalse($container->hasDefinition('ai.retriever.blog.tracing'));
    }

    public function testProcessDoesNothingWhenTracingIsDisabled()
    {
        $container = $this->container(null, false);

        (new TracingCompilerPass())->process($container);

        $this->assertFalse($container->hasDefinition('ai.platform.openai.tracing'));
        $this->assertFalse($container->hasDefinition('ai.agent.support.tracing'));
        $this->assertFalse($container->hasDefinition('ai.toolbox.support.tracing'));
        $this->assertFalse($container->hasDefinition('ai.retriever.blog.tracing'));
    }

    /**
     * @param list<string>|null $instrument
     */
    private function container(?array $instrument, bool $captureContent, bool $captureUser = false, ?string $redactor = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('ai.platform.openai', \stdClass::class)->addTag('ai.platform', ['name' => 'openai']);
        $container->register('ai.agent.support', \stdClass::class)->addTag('ai.agent', ['name' => 'support']);
        $container->register('ai.toolbox.support', \stdClass::class)->addTag('ai.toolbox', ['name' => 'support']);
        $container->register('ai.retriever.blog', \stdClass::class)->addTag('ai.retriever', ['name' => 'blog']);

        if (null !== $instrument) {
            $container->setParameter('.ai.tracing.instrument', $instrument);
            $container->setParameter('.ai.tracing.capture_content', $captureContent);
            $container->setParameter('.ai.tracing.capture_user', $captureUser);
            $container->setParameter('.ai.tracing.content_redactor', $redactor);
        }

        return $container;
    }
}
