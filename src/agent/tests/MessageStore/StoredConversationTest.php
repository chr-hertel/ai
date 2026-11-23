<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\MessageStore;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\MessageStore\StoredConversation;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallState;

final class StoredConversationTest extends TestCase
{
    public function testStoredConversationIsFollowedByTheNewMessages()
    {
        $stored = new MessageBag(Message::ofUser('Hello'), Message::ofAssistant('Hi'));
        $stored->getMetadata()->add('stored', true);
        $input = (new MessageBag(Message::ofUser('How are you?')))->withId($stored->getId());
        $input->getMetadata()->add('input', true);

        $messages = StoredConversation::continue($stored, $input);

        $this->assertTrue($messages->getId()->equals($stored->getId()));
        $texts = [];
        foreach ($messages->getMessages() as $message) {
            $this->assertTrue($message instanceof UserMessage || $message instanceof AssistantMessage);
            $texts[] = $message->asText();
        }
        $this->assertSame(['Hello', 'Hi', 'How are you?'], $texts);
        $this->assertSame(['stored' => true, 'input' => true], $messages->getMetadata()->all());
    }

    public function testStoredMessagesWinOverTheirGivenCopies()
    {
        $stored = new MessageBag(Message::ofUser('Hello'), $suspended = new AssistantMessage((new ToolCall('id1', 'tool1'))->suspend()));

        $messages = StoredConversation::continue($stored, $stored->withToolCall(new ToolCall('id1', 'tool1')));

        $this->assertSame($suspended, $messages->getMessages()[1]);
    }

    public function testApprovalOfASuspendedToolCallIsTakenOver()
    {
        $toolCall = (new ToolCall('id1', 'tool1', ['path' => 'notes.txt']))->suspend();
        $stored = new MessageBag(Message::ofUser('Delete notes.txt'), new AssistantMessage($toolCall));

        $approved = $this->toolCall(StoredConversation::continue($stored, $stored->withToolCall($toolCall->approve(['path' => 'archive/notes.txt']))));

        $this->assertSame(ToolCallState::Approved, $approved->getState());
        $this->assertSame(['path' => 'archive/notes.txt'], $approved->getArguments());
    }

    public function testRejectionOfASuspendedToolCallIsTakenOver()
    {
        $toolCall = (new ToolCall('id1', 'tool1'))->suspend();
        $stored = new MessageBag(Message::ofUser('Delete notes.txt'), new AssistantMessage($toolCall));

        $rejected = $this->toolCall(StoredConversation::continue($stored, $stored->withToolCall($toolCall->reject('Keep it.'))));

        $this->assertSame(ToolCallState::Rejected, $rejected->getState());
        $this->assertSame('Keep it.', $rejected->getReason());
    }

    public function testRepeatedDecisionIsIgnored()
    {
        $approved = (new ToolCall('id1', 'tool1'))->suspend()->approve();
        $stored = new MessageBag(Message::ofUser('Delete notes.txt'), new AssistantMessage($approved));

        $this->assertSame(ToolCallState::Approved, $this->toolCall(StoredConversation::continue($stored, $stored))->getState());
    }

    /**
     * @return iterable<string, array{ToolCall, ToolCall}>
     */
    public static function provideInvalidDecisions(): iterable
    {
        $toolCall = new ToolCall('id1', 'tool1');

        yield 'approval of a requested call' => [$toolCall, new ToolCall('id1', 'tool1', state: ToolCallState::Approved)];
        yield 'rejection of an approved call' => [$toolCall->suspend()->approve(), $toolCall->suspend()->reject()];
        yield 'approval of another tool' => [$toolCall->suspend(), new ToolCall('id1', 'tool2', state: ToolCallState::Approved)];
    }

    #[DataProvider('provideInvalidDecisions')]
    public function testDecisionOnAToolCallThatIsNotSuspendedIsRejected(ToolCall $stored, ToolCall $decided)
    {
        $messages = new MessageBag(Message::ofUser('Delete notes.txt'), new AssistantMessage($stored));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The tool call "%s" with id "id1" can only be approved or rejected while it is suspended.', $decided->getName()));

        StoredConversation::continue($messages, $messages->withToolCall($decided));
    }

    private function toolCall(MessageBag $messages): ToolCall
    {
        $assistantMessage = $messages->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);

        return $assistantMessage->getToolCalls()[0];
    }
}
