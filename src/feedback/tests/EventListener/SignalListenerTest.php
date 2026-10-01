<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Agent\Event\GuardrailTriggered;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Feedback\EventListener\GuardrailSignalListener;
use Symfony\AI\Feedback\EventListener\ToolErrorSignalListener;
use Symfony\AI\Feedback\InMemoryRecorder;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\Feedback\Source;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class SignalListenerTest extends TestCase
{
    public function testFailedToolCallIsRecordedOnTheRun()
    {
        $recorder = new InMemoryRecorder();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ToolCallsExecuted::class, new ToolErrorSignalListener($recorder));

        $results = [new ToolCallResult([new ToolCall('call-1', 'order_lookup')]), new TextResult('Sorry.')];
        $platform = new InMemoryPlatform(static function () use (&$results): ResultInterface {
            return array_shift($results);
        });

        $agent = new Agent($platform, 'gpt-4o', toolbox: new FaultTolerantToolbox(new Toolbox([])), eventDispatcher: $dispatcher);
        $agent->call('Where is my order?', new Context(new RunContext('run-1')))->getContent();

        $feedback = $recorder->all('run-1', Signal::ToolError);
        $this->assertCount(1, $feedback);
        $this->assertFalse($feedback[0]->getValue());
        $this->assertSame(Source::Application, $feedback[0]->getSource());
        $this->assertSame('order_lookup', $feedback[0]->getMetadata()['tool']);
    }

    public function testGuardrailInterventionIsRecordedOnTheRun()
    {
        $recorder = new InMemoryRecorder();

        (new GuardrailSignalListener($recorder))(new GuardrailTriggered('refund_policy', GuardrailTriggered::ACTION_DENIED, 'Outside the refund window.', new RunContext('run-1')));
        (new GuardrailSignalListener($recorder))(new GuardrailTriggered('refund_policy', GuardrailTriggered::ACTION_DENIED));

        $feedback = $recorder->all();
        $this->assertCount(1, $feedback);
        $this->assertSame('guardrail.refund_policy', $feedback[0]->getName());
        $this->assertSame('denied', $feedback[0]->getValue());
    }
}
