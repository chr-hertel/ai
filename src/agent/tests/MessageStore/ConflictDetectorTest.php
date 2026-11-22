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
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\MessageStore\ConflictDetector;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallState;

final class ConflictDetectorTest extends TestCase
{
    public function testMessagesContinuingTheStoredOnesPass()
    {
        $stored = new MessageBag(Message::ofUser('Hello'));

        ConflictDetector::assertContinues($stored, $stored->with(Message::ofAssistant('Hi')));
        ConflictDetector::assertContinues(new MessageBag(), $stored);

        $this->addToAssertionCount(2);
    }

    public function testMessagesDivergingFromTheStoredOnesConflict()
    {
        $stored = new MessageBag(Message::ofUser('Hello'), Message::ofAssistant('Hi'));

        $this->expectException(ConflictException::class);

        ConflictDetector::assertContinues($stored, new MessageBag(Message::ofUser('Hello')));
    }

    public function testVersionIsTheIdOfTheLastMessage()
    {
        $answer = Message::ofAssistant('Hi');

        $this->assertSame('', ConflictDetector::version(new MessageBag()));
        $this->assertSame($answer->getId()->toRfc4122(), ConflictDetector::version(new MessageBag(Message::ofUser('Hello'), $answer)));
    }

    public function testDecidingASuspendedToolCallContinuesTheStoredMessages()
    {
        $suspended = (new ToolCall('id1', 'tool1'))->suspend();
        $stored = new MessageBag(Message::ofUser('Hello'), new AssistantMessage($suspended));

        ConflictDetector::assertContinues($stored, $stored->withToolCall($suspended->approve()));
        ConflictDetector::assertContinues($stored, $stored->withToolCall($suspended->reject()));
        ConflictDetector::assertContinues($stored, $stored);

        $this->addToAssertionCount(3);
    }

    /**
     * @return iterable<string, array{ToolCall, ToolCall}>
     */
    public static function provideConflictingStates(): iterable
    {
        $toolCall = new ToolCall('id1', 'tool1');

        yield 'decided in the meantime' => [$toolCall->suspend()->approve(), $toolCall->suspend()->reject()];
        yield 'approval undone' => [$toolCall->suspend()->approve(), $toolCall->suspend()];
        yield 'suspension undone' => [$toolCall->suspend(), $toolCall];
        yield 'rejection undone' => [$toolCall->reject(), $toolCall];
        yield 'requested call approved' => [$toolCall, new ToolCall('id1', 'tool1', state: ToolCallState::Approved)];
        yield 'requested call rejected' => [$toolCall, $toolCall->reject()];
    }

    #[DataProvider('provideConflictingStates')]
    public function testChangingTheStateOfAStoredToolCallOtherwiseConflicts(ToolCall $stored, ToolCall $changed)
    {
        $messages = new MessageBag(Message::ofUser('Hello'), new AssistantMessage($stored));

        $this->expectException(ConflictException::class);

        ConflictDetector::assertContinues($messages, $messages->withToolCall($changed));
    }

    public function testVersionChangesWithTheStateOfTheToolCalls()
    {
        $toolCall = new ToolCall('id1', 'tool1');
        $requested = new MessageBag(Message::ofUser('Hello'), new AssistantMessage($toolCall));
        $suspended = $requested->withToolCall($toolCall->suspend());

        $versions = [
            ConflictDetector::version($requested),
            ConflictDetector::version($suspended),
            ConflictDetector::version($suspended->withToolCall($toolCall->suspend()->approve())),
            ConflictDetector::version($suspended->withToolCall($toolCall->suspend()->reject())),
        ];

        $this->assertSame($requested->getMessages()[1]->getId()->toRfc4122(), $versions[0]);
        $this->assertCount(4, array_unique($versions));
        foreach ($versions as $version) {
            $this->assertSame(36, \strlen($version));
        }
    }
}
