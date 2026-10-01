<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Event\AgentInvocationCompleted;
use Symfony\AI\Agent\Event\AgentInvocationStarted;
use Symfony\AI\Agent\Event\ModelRequested;
use Symfony\AI\Agent\Event\ModelResponded;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class AgentEventsTest extends TestCase
{
    public function testItDispatchesTheLifecycleEventsOfAnInvocation()
    {
        $dispatched = [];

        $dispatcher = new EventDispatcher();
        foreach ([AgentInvocationStarted::class, ModelRequested::class, ModelResponded::class, AgentInvocationCompleted::class] as $event) {
            $dispatcher->addListener($event, static function (object $event) use (&$dispatched): void {
                $dispatched[] = $event::class;
            });
        }

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', eventDispatcher: $dispatcher);
        $agent->call('Hello there')->getResult();

        $this->assertSame([
            AgentInvocationStarted::class,
            ModelRequested::class,
            ModelResponded::class,
            AgentInvocationCompleted::class,
        ], $dispatched);
    }

    public function testTheStartedEventExposesTheAgentAndTheRequest()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AgentInvocationStarted::class, static function (AgentInvocationStarted $event) use (&$captured): void {
            $captured = $event;
        });

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', name: 'my-agent', eventDispatcher: $dispatcher);
        $agent->call('Hello there')->getResult();

        $this->assertInstanceOf(AgentInvocationStarted::class, $captured);
        $this->assertSame($agent, $captured->getAgent());
        $this->assertSame('gpt-4o', $captured->getRequest()->getModel());
    }

    public function testAListenerCanShortCircuitTheInvocationWithAResult()
    {
        $platform = new InMemoryPlatform(static function (): TextResult {
            throw new \LogicException('The platform must not be invoked.');
        });

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AgentInvocationStarted::class, static function (AgentInvocationStarted $event): void {
            $event->setResult(new TextResult('Served from cache'));
        });

        $agent = new Agent($platform, 'gpt-4o', eventDispatcher: $dispatcher);

        $this->assertSame('Served from cache', $agent->call('Hello there')->getContent());
    }

    public function testTheCompletedEventCarriesTheFinalResult()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AgentInvocationCompleted::class, static function (AgentInvocationCompleted $event) use (&$captured): void {
            $captured = $event;
        });

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', eventDispatcher: $dispatcher);
        $agent->call('Hello there')->getResult();

        $this->assertInstanceOf(AgentInvocationCompleted::class, $captured);
        $this->assertSame('Hi', $captured->getResult()->getResult()->getContent());
    }
}
