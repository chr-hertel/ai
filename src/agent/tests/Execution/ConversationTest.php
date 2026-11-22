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
use Symfony\AI\Agent\Execution\Conversation;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;

final class ConversationTest extends TestCase
{
    public function testPendingToolCallsAreTheUnansweredCallsOfTheLastAssistantMessage()
    {
        $answered = new ToolCall('id1', 'tool1');
        $pending = (new ToolCall('id2', 'tool2'))->suspend();

        $messages = new MessageBag(Message::ofUser('Hello'), new AssistantMessage($answered, $pending), Message::ofToolCall($answered, 'Done'));

        $this->assertSame([$pending], Conversation::pendingToolCalls($messages));
    }

    public function testAnsweredToolCallsAreNotPending()
    {
        $approved = (new ToolCall('id1', 'tool1'))->suspend()->approve();

        $messages = new MessageBag(Message::ofUser('Hello'), new AssistantMessage($approved), Message::ofToolCall($approved, 'Done'));

        $this->assertSame([], Conversation::pendingToolCalls($messages));
    }

    public function testNoToolCallIsPendingOnceTheConversationMovedOn()
    {
        $messages = new MessageBag(Message::ofUser('Hello'), new AssistantMessage(new ToolCall('id1', 'tool1')), Message::ofUser('Never mind'));

        $this->assertSame([], Conversation::pendingToolCalls($messages));
        $this->assertSame([], Conversation::pendingToolCalls(new MessageBag(Message::ofUser('Hello'))));
    }

    public function testToolRoundsOfTurnCountTheToolCallingRoundsSinceTheLastUserMessage()
    {
        $toolCall = new ToolCall('id1', 'tool1');
        $round = [new AssistantMessage($toolCall), Message::ofToolCall($toolCall, 'Done')];

        $this->assertSame(0, Conversation::toolRoundsOfTurn(new MessageBag(...$round, ...[Message::ofUser('Hello')])));
        $this->assertSame(2, Conversation::toolRoundsOfTurn(new MessageBag(Message::ofUser('Hello'), ...$round, ...$round)));
        $this->assertSame(1, Conversation::toolRoundsOfTurn(new MessageBag(Message::ofUser('Hello'), ...$round, ...[Message::ofAssistant('Done')])));
    }

    public function testAbandonedToolCallsAreAnsweredByTheirStateBeforeTheNextMessage()
    {
        $messages = new MessageBag(
            Message::ofUser('Delete the files'),
            new AssistantMessage(
                new ToolCall('id1', 'tool1'),
                (new ToolCall('id2', 'tool1'))->suspend(),
                (new ToolCall('id3', 'tool1'))->suspend()->approve(),
                (new ToolCall('id4', 'tool1'))->reject('Keep it.'),
                (new ToolCall('id5', 'tool1'))->reject(),
            ),
            Message::ofUser('Never mind'),
        );

        $answered = Conversation::answerAbandonedToolCalls($messages);

        $this->assertTrue($answered->getId()->equals($messages->getId()));
        $this->assertSame([
            'The tool call was not answered.',
            'The tool call was suspended and neither approved nor rejected.',
            'The tool call was approved, but not executed.',
            'Keep it.',
            'The tool call was rejected.',
        ], $this->texts(\array_slice($answered->getMessages(), 2, 5)));
        $this->assertSame(['Never mind'], $this->texts([$answered->getMessages()[7]]));
    }

    public function testConversationWithoutAbandonedToolCallsStaysUntouched()
    {
        $toolCall = new ToolCall('id1', 'tool1');
        $answered = new MessageBag(Message::ofUser('Hello'), new AssistantMessage($toolCall), Message::ofToolCall($toolCall, 'Done'), Message::ofUser('Thanks'));
        $suspended = new MessageBag(Message::ofUser('Hello'), new AssistantMessage($toolCall->suspend()));

        $this->assertSame($answered, Conversation::answerAbandonedToolCalls($answered));
        $this->assertSame($suspended, Conversation::answerAbandonedToolCalls($suspended));
    }

    /**
     * @param list<MessageInterface> $messages
     *
     * @return list<string|null>
     */
    private function texts(array $messages): array
    {
        $texts = [];
        foreach ($messages as $message) {
            $this->assertTrue($message instanceof ToolCallMessage || $message instanceof UserMessage);
            $texts[] = $message->asText();
        }

        return $texts;
    }
}
