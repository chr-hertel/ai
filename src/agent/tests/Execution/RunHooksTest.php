<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Execution;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Agent\Context\PromptReference;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Agent\Context\RunScope;
use Symfony\AI\Agent\Event\AgentInvocationFailed;
use Symfony\AI\Agent\Event\ModelRequested;
use Symfony\AI\Agent\Execution\Turn;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class RunHooksTest extends TestCase
{
    public function testEveryRunGetsARunContextEchoedAsResultMetadata()
    {
        $agent = new Agent(new InMemoryPlatform('Hello'), 'gpt-4o');

        $first = $agent->call('Hi');
        $second = $agent->call('Hi');

        $this->assertSame('Hello', $first->getContent());
        $this->assertIsString($runId = $first->getMetadata()->get('run_id'));
        $this->assertNotSame($runId, $second->getMetadata()->get('run_id'));
        $this->assertInstanceOf(RunContext::class, $first->getMetadata()->get('run_context'));
    }

    public function testRunContextOfTheCallerIsKept()
    {
        $agent = new Agent(new InMemoryPlatform('Hello'), 'gpt-4o');

        $execution = $agent->call('Hi', new Context(new RunContext('run-1', 'jane', attributes: ['channel' => 'web'])));

        $this->assertSame('run-1', $execution->getMetadata()->get('run_id'));
        $runContext = $execution->getMetadata()->get('run_context');
        $this->assertInstanceOf(RunContext::class, $runContext);
        $this->assertSame('jane', $runContext->getUserId());
        $this->assertSame(['channel' => 'web'], $runContext->getAttributes());
    }

    public function testEmptyRunIdIsFilledIn()
    {
        $agent = new Agent(new InMemoryPlatform('Hello'), 'gpt-4o');

        $runContext = $agent->call('Hi', new Context(new RunContext('', release: 'v1')))->getMetadata()->get('run_context');

        $this->assertInstanceOf(RunContext::class, $runContext);
        $this->assertNotSame('', $runContext->getRunId());
        $this->assertSame('v1', $runContext->getRelease());
    }

    public function testInstructionReferenceIsRecordedInTheRunContext()
    {
        $agent = new Agent(new InMemoryPlatform('Hello'), 'gpt-4o', instruction: new Instruction('Be nice.', new PromptReference('support/system', 'v3')));

        $runContext = $agent->call('Hi')->getMetadata()->get('run_context');

        $this->assertInstanceOf(RunContext::class, $runContext);
        $this->assertSame('support/system@v3', (string) $runContext->getPrompt());
    }

    public function testModelRequestedListenerCanStopTheRun()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ModelRequested::class, static function (ModelRequested $event): void {
            $event->stop(new TextResult('Stopped.'));
        });

        $agent = new Agent(new InMemoryPlatform(static fn (): ResultInterface => throw new \LogicException('The model must not be invoked.')), 'gpt-4o', eventDispatcher: $dispatcher);

        $this->assertSame('Stopped.', $agent->call('Hi')->getContent());
    }

    public function testModelRequestedListenerCanSwitchTheModel()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ModelRequested::class, static function (ModelRequested $event): void {
            $event->getRequest()->setModel('gpt-4o-mini');
        });

        $platform = new InMemoryPlatform(static fn ($model): string => $model->getName());
        $agent = new Agent($platform, 'gpt-4o', eventDispatcher: $dispatcher);

        $this->assertSame('gpt-4o-mini', $agent->call('Hi')->getContent());
    }

    public function testFailedRunDispatchesAgentInvocationFailed()
    {
        $failures = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AgentInvocationFailed::class, static function (AgentInvocationFailed $event) use (&$failures): void {
            $failures[] = $event;
        });

        $agent = new Agent(new InMemoryPlatform(static fn (): ResultInterface => throw new \RuntimeException('Boom.')), 'gpt-4o', eventDispatcher: $dispatcher);

        try {
            $agent->call('Hi')->getContent();
            $this->fail('The run should have failed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Boom.', $e->getMessage());
        }

        $this->assertCount(1, $failures);
        $this->assertSame('Boom.', $failures[0]->getException()->getMessage());
        $this->assertTrue($failures[0]->getRequest()->getContext()->has(RunContext::class));
    }

    public function testToolCallsExecutedCarriesTheRunAndFailedToolResults()
    {
        $events = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ToolCallsExecuted::class, static function (ToolCallsExecuted $event) use (&$events): void {
            $events[] = $event;
        });

        $results = [new ToolCallResult([new ToolCall('call-1', 'unknown_tool')]), new TextResult('Done.')];
        $platform = new InMemoryPlatform(static function () use (&$results): ResultInterface {
            return array_shift($results);
        });

        $agent = new Agent($platform, 'gpt-4o', toolbox: new FaultTolerantToolbox(new Toolbox([])), eventDispatcher: $dispatcher);
        $execution = $agent->call('Hi', new Context(new RunContext('run-1')));

        $turns = [];
        foreach ($execution as $update) {
            if ($update instanceof Progress && $update->getPayload() instanceof Turn) {
                $turns[] = $update->getPayload();
            }
        }

        $this->assertCount(1, $events);
        $this->assertSame('run-1', $events[0]->getRequest()?->getContext()->get(RunContext::class)?->getRunId());
        $this->assertTrue($events[0]->getToolResults()[0]->isFailure());
        $this->assertCount(2, $turns);
        $this->assertNotNull($turns[0]->getDuration());
    }

    public function testToolCallEventsSeeTheRun()
    {
        $runIds = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ToolCallRequested::class, static function (ToolCallRequested $event) use (&$runIds): void {
            $runIds[] = $event->getRunContext()?->getRunId();
            $event->deny('Not allowed.');
        });

        $results = [new ToolCallResult([new ToolCall('call-1', 'tool_no_params')]), new TextResult('Done.')];
        $platform = new InMemoryPlatform(static function () use (&$results): ResultInterface {
            return array_shift($results);
        });

        $agent = new Agent($platform, 'gpt-4o', toolbox: new Toolbox([new ToolNoParams()], eventDispatcher: $dispatcher));
        $agent->call('Hi', new Context(new RunContext('run-1')))->getContent();

        $this->assertSame(['run-1'], $runIds);
        $this->assertNull(RunScope::current());
    }
}
