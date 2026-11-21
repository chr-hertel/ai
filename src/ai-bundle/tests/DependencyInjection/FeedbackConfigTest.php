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
use Symfony\AI\Agent\Event\GuardrailTriggered;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\AiBundle\AiBundle;
use Symfony\AI\AiBundle\Command\SetupFeedbackCommand;
use Symfony\AI\Feedback\Bridge\Doctrine\DoctrineRecorder;
use Symfony\AI\Feedback\Bridge\Langfuse\LangfuseRecorder;
use Symfony\AI\Feedback\Bridge\Phoenix\PhoenixRecorder;
use Symfony\AI\Feedback\ChainRecorder;
use Symfony\AI\Feedback\EventListener\ToolErrorSignalListener;
use Symfony\AI\Feedback\FeedbackRecorderInterface;
use Symfony\AI\Feedback\Messenger\MessengerRecorder;
use Symfony\AI\Feedback\Messenger\RecordFeedbackHandler;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class FeedbackConfigTest extends TestCase
{
    public function testFeedbackIsDisabledByDefault()
    {
        $container = $this->buildContainer([]);

        $this->assertFalse($container->hasAlias(FeedbackRecorderInterface::class));
        $this->assertFalse($container->hasDefinition('ai.feedback.listener.tool_errors'));
    }

    public function testRecordersAreChained()
    {
        $container = $this->buildContainer(['feedback' => ['recorders' => [
            'langfuse' => ['public_key' => 'pk', 'secret_key' => 'sk', 'host' => 'http://langfuse'],
            'phoenix' => ['api_key' => 'key'],
            'doctrine' => ['connection' => 'feedback'],
            'services' => ['app.recorder'],
        ]]]);

        $this->assertSame('ai.feedback.recorder', (string) $container->getAlias(FeedbackRecorderInterface::class));
        $this->assertSame('ai.feedback.recorder.chain', (string) $container->getAlias('ai.feedback.recorder'));

        $chain = $container->getDefinition('ai.feedback.recorder.chain');
        $this->assertSame(ChainRecorder::class, $chain->getClass());
        $this->assertEquals([
            new Reference('app.recorder'),
            new Reference('ai.feedback.recorder.langfuse'),
            new Reference('ai.feedback.recorder.phoenix'),
            new Reference('ai.feedback.recorder.doctrine'),
        ], $chain->getArgument(0));

        $langfuse = $container->getDefinition('ai.feedback.recorder.langfuse');
        $this->assertSame(LangfuseRecorder::class, $langfuse->getClass());
        $this->assertEquals([new Reference('http_client'), 'http://langfuse', 'pk', 'sk'], $langfuse->getArguments());

        $phoenix = $container->getDefinition('ai.feedback.recorder.phoenix');
        $this->assertSame(PhoenixRecorder::class, $phoenix->getClass());
        $this->assertEquals([new Reference('http_client'), 'http://localhost:6006', 'key'], $phoenix->getArguments());

        $doctrine = $container->getDefinition('ai.feedback.recorder.doctrine');
        $this->assertSame(DoctrineRecorder::class, $doctrine->getClass());
        $this->assertEquals([new Reference('doctrine.dbal.feedback_connection'), 'ai_feedback'], $doctrine->getArguments());
        $this->assertSame(SetupFeedbackCommand::class, $container->getDefinition('ai.command.setup_feedback')->getClass());
    }

    public function testSignalsAreRecorded()
    {
        $container = $this->buildContainer(['feedback' => ['recorders' => ['services' => ['app.recorder']]]]);

        $toolErrors = $container->getDefinition('ai.feedback.listener.tool_errors');
        $this->assertSame(ToolErrorSignalListener::class, $toolErrors->getClass());
        $this->assertEquals(new Reference('ai.feedback.recorder'), $toolErrors->getArgument(0));
        $this->assertSame([['event' => ToolCallsExecuted::class]], $toolErrors->getTag('kernel.event_listener'));
        $this->assertSame([['event' => GuardrailTriggered::class]], $container->getDefinition('ai.feedback.listener.guardrails')->getTag('kernel.event_listener'));
    }

    public function testSignalsCanBeDisabled()
    {
        $container = $this->buildContainer(['feedback' => ['recorders' => ['services' => ['app.recorder']], 'signals' => ['tool_errors' => false, 'guardrails' => false]]]);

        $this->assertFalse($container->hasDefinition('ai.feedback.listener.tool_errors'));
        $this->assertFalse($container->hasDefinition('ai.feedback.listener.guardrails'));
    }

    public function testAsyncRecordingGoesThroughMessenger()
    {
        $container = $this->buildContainer(['feedback' => ['recorders' => ['services' => ['app.recorder']], 'async' => true]]);

        $recorder = $container->getDefinition('ai.feedback.recorder');
        $this->assertSame(MessengerRecorder::class, $recorder->getClass());
        $this->assertEquals([new Reference('messenger.default_bus')], $recorder->getArguments());

        $handler = $container->getDefinition('ai.feedback.record_handler');
        $this->assertSame(RecordFeedbackHandler::class, $handler->getClass());
        $this->assertEquals([new Reference('ai.feedback.recorder.chain')], $handler->getArguments());
        $this->assertTrue($handler->hasTag('messenger.message_handler'));
    }

    public function testFeedbackNeedsARecorder()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Configure at least one feedback recorder.');

        $this->buildContainer(['feedback' => true]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildContainer(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.build_dir', 'public');

        (new AiBundle())->getContainerExtension()->load(['ai' => $config], $container);

        return $container;
    }
}
