<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

final class SequentialToolExecutorTest extends TestCase
{
    public function testItExecutesTheToolCallsInOrder()
    {
        $toolCall1 = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolCall2 = new ToolCall('id2', 'tool2', ['arg2' => 'value2']);

        $executed = [];
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(static function (ToolCall $toolCall) use (&$executed): ToolResult {
                $executed[] = $toolCall->getName();

                return new ToolResult($toolCall, 'Result of '.$toolCall->getName());
            });

        $executor = new SequentialToolExecutor($toolbox);
        $execution = $executor->execute(new ToolCallResult([$toolCall1, $toolCall2]));

        $this->assertSame(['tool1', 'tool2'], $executed);
        $this->assertCount(2, $execution->getResults());
        $this->assertSame($toolCall1, $execution->getResults()[0]->getToolCall());
        $this->assertSame($toolCall2, $execution->getResults()[1]->getToolCall());
    }

    public function testItReturnsTheAssistantMessageFollowedByOneMessagePerToolCall()
    {
        $toolCall1 = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolCall2 = new ToolCall('id2', 'tool2', ['arg2' => 'value2']);

        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturnCallback(static fn (ToolCall $toolCall): ToolResult => new ToolResult($toolCall, 'Result of '.$toolCall->getName()));

        $executor = new SequentialToolExecutor($toolbox);
        $messages = $executor->execute(new ToolCallResult([$toolCall1, $toolCall2]))->getMessages();

        $this->assertCount(3, $messages);

        $this->assertInstanceOf(AssistantMessage::class, $messages[0]);
        $this->assertTrue($messages[0]->hasToolCalls());
        $this->assertSame([$toolCall1, $toolCall2], $messages[0]->getToolCalls());

        $this->assertInstanceOf(ToolCallMessage::class, $messages[1]);
        $this->assertSame($toolCall1, $messages[1]->getToolCall());

        $this->assertInstanceOf(ToolCallMessage::class, $messages[2]);
        $this->assertSame($toolCall2, $messages[2]->getToolCall());
    }
}
