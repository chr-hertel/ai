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
use Symfony\AI\Agent\Event\ExecutionCompleted;
use Symfony\AI\Agent\Event\ExecutionStarted;
use Symfony\AI\Agent\Event\ModelRequested;
use Symfony\AI\Agent\Event\ModelResponded;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class AgentEventsTest extends TestCase
{
    public function testItDispatchesTheLifecycleEventsOfAnExecution()
    {
        $dispatched = [];

        $dispatcher = new EventDispatcher();
        foreach ([ExecutionStarted::class, ModelRequested::class, ModelResponded::class, ExecutionCompleted::class] as $event) {
            $dispatcher->addListener($event, static function (object $event) use (&$dispatched): void {
                $dispatched[] = $event::class;
            });
        }

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', eventDispatcher: $dispatcher);
        $agent->call('Hello there')->getResult();

        $this->assertSame([
            ExecutionStarted::class,
            ModelRequested::class,
            ModelResponded::class,
            ExecutionCompleted::class,
        ], $dispatched);
    }

    public function testTheStartedEventExposesTheAgentAndTheRequest()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ExecutionStarted::class, static function (ExecutionStarted $event) use (&$captured): void {
            $captured = $event;
        });

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', name: 'my-agent', eventDispatcher: $dispatcher);
        $agent->call('Hello there')->getResult();

        $this->assertInstanceOf(ExecutionStarted::class, $captured);
        $this->assertSame($agent, $captured->getAgent());
        $this->assertSame('gpt-4o', $captured->getRequest()->getModel());
    }

    public function testAListenerCanShortCircuitTheExecutionWithAResult()
    {
        $platform = new InMemoryPlatform(static function (): TextResult {
            throw new \LogicException('The platform must not be invoked.');
        });

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ExecutionStarted::class, static function (ExecutionStarted $event): void {
            $event->setResult(new TextResult('Served from cache'));
        });

        $agent = new Agent($platform, 'gpt-4o', eventDispatcher: $dispatcher);

        $this->assertSame('Served from cache', $agent->call('Hello there')->getContent());
    }

    public function testTheCompletedEventCarriesTheFinalResult()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ExecutionCompleted::class, static function (ExecutionCompleted $event) use (&$captured): void {
            $captured = $event;
        });

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', eventDispatcher: $dispatcher);
        $agent->call('Hello there')->getResult();

        $this->assertInstanceOf(ExecutionCompleted::class, $captured);
        $this->assertSame('Hi', $captured->getResult()->getResult()->getContent());
    }
}
