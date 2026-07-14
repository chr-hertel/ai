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
use Symfony\AI\Agent\Event\HandoffCompleted;
use Symfony\AI\Agent\Event\HandoffRequested;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Handoff\Decision;
use Symfony\AI\Agent\Handoff\Handoff;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class HandoffTest extends TestCase
{
    public function testItDelegatesToTheAgentTheModelPicked()
    {
        $technical = new MockAgent(['Hello there' => 'The technical agent answered.'], 'technical');

        $agent = new Agent(
            $this->decidingPlatform('technical'),
            'gpt-4o',
            handoffs: [new Handoff($technical, 'bugs, errors and other technical problems')],
        );

        $this->assertSame('The technical agent answered.', $agent->call('Hello there')->getContent());
    }

    public function testItAnswersItselfWhenTheModelPicksNoAgent()
    {
        $technical = new MockAgent([], 'technical');

        $agent = new Agent(
            $this->decidingPlatform('', 'The orchestrator answers.'),
            'gpt-4o',
            handoffs: [new Handoff($technical, 'bugs, errors and other technical problems')],
        );

        $this->assertSame('The orchestrator answers.', $agent->call('Hello there')->getContent());
    }

    public function testItAnswersItselfWhenTheModelPicksAnUnknownAgent()
    {
        $technical = new MockAgent([], 'technical');

        $agent = new Agent(
            $this->decidingPlatform('billing', 'The orchestrator answers.'),
            'gpt-4o',
            handoffs: [new Handoff($technical, 'bugs, errors and other technical problems')],
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
            handoffs: [new Handoff($technical, 'technical problems', static fn (): bool => false)],
        );

        $this->assertSame('The orchestrator answers.', $agent->call('Hello there')->getContent());
    }

    public function testItReportsTheHandoffAsProgress()
    {
        $technical = new MockAgent(['Hello there' => 'The technical agent answered.'], 'technical');

        $agent = new Agent(
            $this->decidingPlatform('technical'),
            'gpt-4o',
            handoffs: [new Handoff($technical, 'bugs, errors and other technical problems')],
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
            handoffs: [new Handoff($technical, 'bugs, errors and other technical problems')],
            eventDispatcher: $dispatcher,
        );
        $agent->call('Hello there')->getResult();

        $this->assertInstanceOf(HandoffRequested::class, $requested);
        $this->assertSame($technical, $requested->getTarget());

        $this->assertInstanceOf(HandoffCompleted::class, $completed);
        $this->assertSame('The technical agent answered.', $completed->getResult()->getContent());
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
            handoffs: [new Handoff($technical, 'bugs, errors and other technical problems')],
            eventDispatcher: $dispatcher,
        );

        $this->assertSame('The orchestrator answers.', $agent->call('Hello there')->getContent());
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
