<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Event;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsRequested;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallState;

final class ToolCallsRequestedTest extends TestCase
{
    public function testToolCallsStayRequestedByDefault()
    {
        $event = new ToolCallsRequested([new ToolCall('id1', 'tool1')], [], new MessageBag());

        $this->assertSame(ToolCallState::Requested, $event->getToolCalls()[0]->getState());
    }

    public function testSuspendAndRejectChangeTheStateOfTheToolCall()
    {
        $suspended = new ToolCall('id1', 'tool1');
        $rejected = new ToolCall('id2', 'tool2');
        $event = new ToolCallsRequested([$suspended, $rejected], [], new MessageBag());

        $event->suspend($suspended, 'Needs approval.');
        $event->reject($rejected, 'Not allowed.');

        [$first, $second] = $event->getToolCalls();
        $this->assertSame(ToolCallState::Suspended, $first->getState());
        $this->assertSame('Needs approval.', $first->getReason());
        $this->assertSame(ToolCallState::Rejected, $second->getState());
        $this->assertSame('Not allowed.', $second->getReason());
    }

    public function testRejectionWinsOverSuspension()
    {
        $toolCall = new ToolCall('id1', 'tool1');
        $event = new ToolCallsRequested([$toolCall], [], new MessageBag());

        $event->reject($toolCall, 'Not allowed.');
        $event->suspend($toolCall);

        $this->assertSame(ToolCallState::Rejected, $event->getToolCalls()[0]->getState());

        $other = new ToolCallsRequested([$toolCall], [], new MessageBag());
        $other->suspend($toolCall);
        $other->reject($toolCall, 'Not allowed.');

        $this->assertSame(ToolCallState::Rejected, $other->getToolCalls()[0]->getState());
    }

    public function testUnknownToolCallIsRejected()
    {
        $event = new ToolCallsRequested([new ToolCall('id1', 'tool1')], [], new MessageBag());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The round has no tool call with id "id2".');

        $event->suspend(new ToolCall('id2', 'tool2'));
    }
}
