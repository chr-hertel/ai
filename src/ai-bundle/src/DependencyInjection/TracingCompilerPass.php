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

use Symfony\AI\OpenTelemetryBridge\Agent\TracingAgent;
use Symfony\AI\OpenTelemetryBridge\Platform\TracingPlatform;
use Symfony\AI\OpenTelemetryBridge\Store\TracingRetriever;
use Symfony\AI\OpenTelemetryBridge\Toolbox\TracingToolbox;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Decorates the tagged services with the OpenTelemetry bridge when tracing is enabled.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TracingCompilerPass implements CompilerPassInterface
{
    // Inside the profiler's Traceable* decorators and FaultTolerantToolbox (-1024), outside SpeechAgent (-512)
    public const PRIORITY = -768;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('.ai.tracing.instrument')) {
            return;
        }

        /** @var list<string> $instrument */
        $instrument = $container->getParameter('.ai.tracing.instrument');
        $captureContent = $container->getParameter('.ai.tracing.capture_content');
        $tracer = new Reference('ai.tracing.tracer');
        $userIdResolver = $container->getParameter('.ai.tracing.capture_user') ? new Reference('ai.tracing.user_id_resolver') : null;

        if (\in_array('platform', $instrument, true)) {
            foreach ($container->findTaggedServiceIds('ai.platform') as $id => $tags) {
                $this->decorate($container, $id, TracingPlatform::class, [$tracer, $tags[0]['name'] ?? $id, $captureContent, $userIdResolver]);
            }
        }

        if (\in_array('agent', $instrument, true)) {
            foreach (array_keys($container->findTaggedServiceIds('ai.agent')) as $id) {
                $this->decorate($container, $id, TracingAgent::class, [$tracer, $captureContent, $userIdResolver]);
            }
        }

        if (\in_array('toolbox', $instrument, true)) {
            foreach (array_keys($container->findTaggedServiceIds('ai.toolbox')) as $id) {
                $this->decorate($container, $id, TracingToolbox::class, [$tracer, $captureContent]);
            }
        }

        if (\in_array('retriever', $instrument, true)) {
            foreach ($container->findTaggedServiceIds('ai.retriever') as $id => $tags) {
                $this->decorate($container, $id, TracingRetriever::class, [$tracer, $tags[0]['name'] ?? $id]);
            }
        }
    }

    /**
     * @param class-string $class
     * @param list<mixed>  $arguments
     */
    private function decorate(ContainerBuilder $container, string $id, string $class, array $arguments): void
    {
        $container->setDefinition($id.'.tracing', (new Definition($class))
            ->setDecoratedService($id, priority: self::PRIORITY)
            ->setArguments([new Reference('.inner'), ...$arguments]));
    }
}
