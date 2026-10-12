<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Handoff;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Event\HandoffCompleted;
use Symfony\AI\Agent\Event\HandoffRequested;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Handoff\Decision;
use Symfony\AI\Agent\Handoff\Handoff;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Agent\Tests\Fixtures\SuspendingConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class HandoffTest extends TestCase
{
    public function testItDelegatesToTheAgentTheModelPicked()
    {
        $technical = new MockAgent(['Hello there' => 'The technical agent answered.'], 'technical');

        $agent = new Agent(
            $this->decidingPlatform('technical'),
            'gpt-4o',
            context: new Context(new Handoff($technical, 'bugs, errors and other technical problems')),
        );

        $this->assertSame('The technical agent answered.', $agent->call('Hello there')->getContent());
    }

    public function testItDelegatesWhenAReasoningModelWrapsTheDecisionIntoAMultiPartResult()
    {
        $technical = new MockAgent(['Hello there' => 'The technical agent answered.'], 'technical');

        $platform = new InMemoryPlatform(static fn (): ResultInterface => new MultiPartResult([
            new ThinkingResult('The user reports an error.'),
            new ObjectResult(new Decision('technical', 'Because of the topic.')),
        ]));

        $agent = new Agent($platform, 'gpt-5-mini', context: new Context(new Handoff($technical, 'bugs, errors and other technical problems')));

        $this->assertSame('The technical agent answered.', $agent->call('Hello there')->getContent());
    }

    public function testTheResultNamesTheAgentItWasDelegatedTo()
    {
        $technical = new MockAgent(['Hello there' => 'The technical agent answered.'], 'technical');

        $agent = new Agent(
            $this->decidingPlatform('technical'),
            'gpt-4o',
            context: new Context(new Handoff($technical, 'bugs, errors and other technical problems')),
        );

        $this->assertSame('technical', $agent->call('Hello there')->getResult()->getMetadata()->get('delegated_to'));
    }

    public function testItAnswersItselfWhenTheModelPicksNoAgent()
    {
        $technical = new MockAgent([], 'technical');

        $agent = new Agent(
            $this->decidingPlatform('', 'The orchestrator answers.'),
            'gpt-4o',
            context: new Context(new Handoff($technical, 'bugs, errors and other technical problems')),
        );

        $this->assertSame('The orchestrator answers.', $agent->call('Hello there')->getContent());
    }

    public function testItAnswersItselfWhenTheModelPicksAnUnknownAgent()
    {
        $technical = new MockAgent([], 'technical');

        $agent = new Agent(
            $this->decidingPlatform('billing', 'The orchestrator answers.'),
            'gpt-4o',
            context: new Context(new Handoff($technical, 'bugs, errors and other technical problems')),
        );

        $this->assertSame('The orchestrator answers.', $agent->call('Hello there')->getContent());
    }

    public function testAHandoffWithAFailingConditionIsNotApplicable()
    {
        $technical = new MockAgent([], 'technical');

        $agent = new Agent(
            // no handoff is applicable, so the model is never asked to decide
            new InMemoryPlatform('The orchestrator answers.'),
            'gpt-4o',
            context: new Context(new Handoff($technical, 'technical problems', static fn (): bool => false)),
        );

        $this->assertSame('The orchestrator answers.', $agent->call('Hello there')->getContent());
    }

    public function testItReportsTheHandoffAsProgress()
    {
        $technical = new MockAgent(['Hello there' => 'The technical agent answered.'], 'technical');

        $agent = new Agent(
            $this->decidingPlatform('technical'),
            'gpt-4o',
            context: new Context(new Handoff($technical, 'bugs, errors and other technical problems')),
        );

        $stages = [];
        foreach ($agent->call('Hello there') as $update) {
            if ($update instanceof Progress) {
                $stages[] = $update->getStage();
            }
        }

        $this->assertContains('handoff', $stages);
    }

    public function testItDispatchesTheHandoffEvents()
    {
        $technical = new MockAgent(['Hello there' => 'The technical agent answered.'], 'technical');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(HandoffRequested::class, static function (HandoffRequested $event) use (&$requested): void {
            $requested = $event;
        });
        $dispatcher->addListener(HandoffCompleted::class, static function (HandoffCompleted $event) use (&$completed): void {
            $completed = $event;
        });

        $agent = new Agent(
            $this->decidingPlatform('technical'),
            'gpt-4o',
            context: new Context(new Handoff($technical, 'bugs, errors and other technical problems')),
            eventDispatcher: $dispatcher,
        );
        $agent->call('Hello there')->getResult();

        $this->assertInstanceOf(HandoffRequested::class, $requested);
        $this->assertSame($technical, $requested->getTarget());

        $this->assertInstanceOf(HandoffCompleted::class, $completed);
        $this->assertSame('The technical agent answered.', $completed->getResult()->getContent());
    }

    public function testTheHandoffCompletedEventArrivesBeforeTheResult()
    {
        $technical = new MockAgent(['Hello there' => 'The technical agent answered.'], 'technical');

        $order = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(HandoffCompleted::class, static function () use (&$order): void {
            $order[] = 'handoff_completed';
        });

        $agent = new Agent(
            $this->decidingPlatform('technical'),
            'gpt-4o',
            context: new Context(new Handoff($technical, 'bugs, errors and other technical problems')),
            eventDispatcher: $dispatcher,
        );

        foreach ($agent->call('Hello there') as $update) {
            if ($update instanceof ResultUpdate) {
                $order[] = 'result';
            }
        }

        $this->assertSame(['handoff_completed', 'result'], $order);
    }

    public function testAListenerCanCancelTheHandoff()
    {
        $technical = new MockAgent([], 'technical');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(HandoffRequested::class, static function (HandoffRequested $event): void {
            $event->setTarget(null);
        });

        $agent = new Agent(
            $this->decidingPlatform('technical', 'The orchestrator answers.'),
            'gpt-4o',
            context: new Context(new Handoff($technical, 'bugs, errors and other technical problems')),
            eventDispatcher: $dispatcher,
        );

        $this->assertSame('The orchestrator answers.', $agent->call('Hello there')->getContent());
    }

    public function testHandoffsCanBeGivenPerCallThroughTheContext()
    {
        $technical = new MockAgent(['Hello there' => 'The technical agent answered.'], 'technical');

        $agent = new Agent($this->decidingPlatform('technical'), 'gpt-4o');
        $context = new Context(new Handoff($technical, 'bugs, errors and other technical problems'));

        $this->assertSame('The technical agent answered.', $agent->call('Hello there', $context)->getContent());
    }

    public function testTheTargetGetsTheContextWithoutTheInstructionAndHandoffsOfTheDelegatingAgent()
    {
        $item = new \stdClass();

        $target = $this->createMock(AgentInterface::class);
        $target->method('getName')->willReturn('technical');
        $target->expects($this->once())
            ->method('call')
            ->with($this->anything(), $this->callback(static fn (Context $context): bool => [$item] === $context->all()))
            ->willReturn(new Execution(static fn (): \Generator => yield new ResultUpdate(new TextResult('The technical agent answered.'))));

        $agent = new Agent(
            $this->decidingPlatform('technical'),
            'gpt-4o',
            instruction: 'You route requests.',
            context: new Context(new Handoff($target, 'bugs, errors and other technical problems')),
        );

        $this->assertSame('The technical agent answered.', $agent->call('Hello there', new Context($item))->getContent());
    }

    public function testTheUpdatesOfTheTargetAreForwarded()
    {
        $target = $this->createStub(AgentInterface::class);
        $target->method('getName')->willReturn('technical');
        $target->method('call')->willReturn(new Execution(static function (): \Generator {
            yield new Progress(Progress::STAGE_TOOL_CALL, 'Executing tool.');
            yield new Progress(Progress::STAGE_DELTA, 'Streaming answer.');
            yield new ResultUpdate(new TextResult('The technical agent answered.'));
        }));

        $agent = new Agent(
            $this->decidingPlatform('technical'),
            'gpt-4o',
            context: new Context(new Handoff($target, 'bugs, errors and other technical problems')),
        );

        $updates = iterator_to_array($agent->call('Hello there'), false);

        $this->assertCount(4, $updates);
        $this->assertInstanceOf(Progress::class, $updates[0]);
        $this->assertSame(Progress::STAGE_HANDOFF, $updates[0]->getStage());
        $this->assertSame('technical', $updates[0]->getPayload());
        $this->assertInstanceOf(Progress::class, $updates[1]);
        $this->assertSame(Progress::STAGE_TOOL_CALL, $updates[1]->getStage());
        $this->assertInstanceOf(Progress::class, $updates[2]);
        $this->assertSame(Progress::STAGE_DELTA, $updates[2]->getStage());
        $this->assertInstanceOf(ResultUpdate::class, $updates[3]);
        $this->assertSame('The technical agent answered.', $updates[3]->getResult()->getContent());
    }

    public function testCancelingWhileTheModelDecidesCancelsTheRequest()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('cancel');

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn(new DeferredResult(new SuspendingConverter(), new RawHttpResult($response)));

        $agent = new Agent($platform, 'gpt-4o', context: new Context(new Handoff(new MockAgent([], 'technical'), 'technical problems')));
        $execution = $agent->call('Hello there');

        $fiber = new \Fiber(static fn (): ResultInterface => $execution->getResult());
        $fiber->start();

        $execution->cancel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The agent execution was canceled.');

        $fiber->resume();
    }

    public function testCancelingPropagatesIntoTheTarget()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('cancel');

        $targetPlatform = $this->createStub(PlatformInterface::class);
        $targetPlatform->method('invoke')->willReturn(new DeferredResult(new SuspendingConverter(), new RawHttpResult($response)));
        $target = new Agent($targetPlatform, 'gpt-4o', name: 'technical');

        $agent = new Agent(
            $this->decidingPlatform('technical'),
            'gpt-4o',
            context: new Context(new Handoff($target, 'technical problems')),
        );
        $execution = $agent->call('Hello there');

        $fiber = new \Fiber(static fn (): ResultInterface => $execution->getResult());
        $fiber->start();

        $execution->cancel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The agent execution was canceled.');

        $fiber->resume();
    }

    public function testAHandoffRequiresADescription()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A handoff must have a non-empty description.');

        new Handoff(new MockAgent([], 'technical'), '');
    }

    /**
     * A platform that first answers the agent-selection question with the given agent name, and then - if the
     * orchestrator ends up answering itself - with the given fallback text.
     */
    private function decidingPlatform(string $agentName, string $answer = 'The orchestrator answers.'): InMemoryPlatform
    {
        $invocation = 0;

        return new InMemoryPlatform(static function () use (&$invocation, $agentName, $answer): ResultInterface {
            if (0 === $invocation++) {
                return new ObjectResult(new Decision($agentName, 'Because of the topic.'));
            }

            return new TextResult($answer);
        });
    }
}
