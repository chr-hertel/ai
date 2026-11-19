<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Run;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Execution\Turn;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Agent\Run\RunScope;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class RunContextTest extends TestCase
{
    public function testEveryRunGetsTheExecutionIdAsRunId()
    {
        $agent = new Agent(MockPlatformFactory::createPlatform('Hello'), 'gpt-4o');

        $execution = $agent->call('Hi');
        $metadata = $execution->getResult()->getMetadata();

        $this->assertSame($execution->getId()->toRfc4122(), $metadata->get('run_id'));
        $this->assertInstanceOf(RunContext::class, $metadata->get('run_context'));
        $this->assertSame($metadata->get('run_id'), $metadata->get('run_context')->getRunId());
    }

    public function testRunContextOfTheCallerIsKept()
    {
        $options = [];
        $platform = MockPlatformFactory::createPlatform(static function ($model, $payload, array $passed) use (&$options): string {
            $options = $passed;

            return 'Hello';
        });
        $agent = new Agent($platform, 'gpt-4o');

        $metadata = $agent->call('Hi', ['run_context' => new RunContext('run-1', 'customer-1', release: '2026.11')])->getResult()->getMetadata();

        $this->assertSame('run-1', $metadata->get('run_id'));
        $this->assertSame('customer-1', $metadata->get('run_context')->getUserId());
        $this->assertSame('2026.11', $metadata->get('run_context')->getRelease());
        $this->assertArrayNotHasKey('run_context', $options);
    }

    public function testRunContextOptionMustBeARunContext()
    {
        $agent = new Agent(MockPlatformFactory::createPlatform('Hello'), 'gpt-4o');

        $this->expectException(InvalidArgumentException::class);

        $agent->call('Hi', ['run_context' => 'run-1']);
    }

    public function testToolsAndListenersSeeTheRun()
    {
        $seen = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ToolCallRequested::class, static function () use (&$seen): void {
            $seen[] = RunScope::current()?->getRunId();
        });

        $agent = new Agent($this->platform('tool_no_params'), 'gpt-4o', toolbox: new Toolbox([new ToolNoParams()], eventDispatcher: $dispatcher));
        $agent->call('Hi', ['run_context' => new RunContext('run-1')])->getResult();

        $this->assertSame(['run-1'], $seen);
        $this->assertNull(RunScope::current());
    }

    public function testInterleavedExecutionsSeeTheirOwnRun()
    {
        $seen = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ToolCallRequested::class, static function () use (&$seen): void {
            $seen[] = RunScope::current()?->getRunId();
        });

        $toolbox = new Toolbox([new ToolNoParams()], eventDispatcher: $dispatcher);
        $first = (new Agent($this->platform('tool_no_params'), 'gpt-4o', toolbox: $toolbox))->call('Hi', ['run_context' => new RunContext('run-1')]);
        $second = (new Agent($this->platform('tool_no_params'), 'gpt-4o', toolbox: $toolbox))->call('Hi', ['run_context' => new RunContext('run-2')]);

        $firstUpdates = (static fn (): \Generator => yield from $first)();
        $secondUpdates = (static fn (): \Generator => yield from $second)();
        while ($firstUpdates->valid() || $secondUpdates->valid()) {
            if ($firstUpdates->valid()) {
                $this->assertNull(RunScope::current());
                $firstUpdates->next();
            }

            if ($secondUpdates->valid()) {
                $secondUpdates->next();
            }
        }

        $this->assertSame(['run-1', 'run-2'], $seen);
    }

    public function testTurnsReachTheEventsAndTheOutput()
    {
        $executed = null;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ToolCallsExecuted::class, static function (ToolCallsExecuted $event) use (&$executed): void {
            $executed = $event;
        });

        $outputProcessor = new class implements OutputProcessorInterface {
            public ?Output $output = null;

            public function processOutput(Output $output): void
            {
                $this->output = $output;
            }
        };

        $agent = new Agent($this->platform('unknown_tool'), 'gpt-4o', outputProcessors: [$outputProcessor], toolbox: new FaultTolerantToolbox(new Toolbox([])), eventDispatcher: $dispatcher);

        $turns = [];
        foreach ($agent->call('Hi', ['run_context' => new RunContext('run-1')]) as $update) {
            if ($update instanceof Progress && Progress::STAGE_TURN === $update->getStage()) {
                $turns[] = $update->getPayload();
            }
        }

        $this->assertInstanceOf(ToolCallsExecuted::class, $executed);
        $this->assertTrue($executed->getToolResults()[0]->isFailure());
        $this->assertSame($turns[0], $executed->getTurn());

        $output = $outputProcessor->output;
        $this->assertInstanceOf(Output::class, $output);
        $this->assertSame('run-1', $output->getRunContext()->getRunId());
        $this->assertSame($turns, $output->getTurns());
        $this->assertCount(2, $turns);
        $this->assertContainsOnlyInstancesOf(Turn::class, $turns);
        $this->assertTrue($turns[0]->hasToolResults());
        $this->assertFalse($turns[1]->hasToolResults());
        $this->assertNotNull($turns[0]->getDuration());
    }

    private function platform(string $toolName): PlatformInterface
    {
        $results = [new ToolCallResult([new ToolCall('call-1', $toolName)]), new TextResult('Done.')];

        return MockPlatformFactory::createPlatform(static function () use (&$results): ResultInterface {
            return array_shift($results);
        });
    }
}
