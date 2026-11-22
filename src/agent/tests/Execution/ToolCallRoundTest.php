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
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Execution\ToolCallRound;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallState;

final class ToolCallRoundTest extends TestCase
{
    public function testRequestedAndApprovedToolCallsAreExecutedAndAnsweredInOrder()
    {
        $requested = new ToolCall('id1', 'tool1');
        $approved = (new ToolCall('id2', 'tool2', ['path' => 'notes.txt']))->suspend()->approve(['path' => 'archive/notes.txt']);
        $executed = [];

        [$messages, , $toolResults] = $this->runRound($this->round($executed), [$requested, $approved]);

        $this->assertSame([[], ['path' => 'archive/notes.txt']], $executed);
        $this->assertCount(2, $toolResults);
        $this->assertSame(['id1' => 'tool1 done', 'id2' => 'tool2 done'], $this->answers($messages));
    }

    public function testRejectedToolCallIsAnsweredWithItsReasonInsteadOfBeingExecuted()
    {
        $executed = [];

        [$messages, , $toolResults] = $this->runRound($this->round($executed), [
            (new ToolCall('id1', 'tool1'))->reject('Keep it.'),
            (new ToolCall('id2', 'tool2'))->suspend()->reject(),
        ]);

        $this->assertSame([], $executed);
        $this->assertSame([], $toolResults);
        $this->assertSame(['id1' => 'Keep it.', 'id2' => 'The tool call was rejected.'], $this->answers($messages));
    }

    public function testSuspendedToolCallIsLeftUnanswered()
    {
        $executed = [];

        [$messages] = $this->runRound($this->round($executed), [new ToolCall('id1', 'tool1'), (new ToolCall('id2', 'tool2'))->suspend()]);

        $this->assertSame([[]], $executed);
        $this->assertSame(['id1' => 'tool1 done'], $this->answers($messages));
    }

    public function testSourcesOfTheExecutedToolCallsAreCollected()
    {
        $source = new Source('Wikipedia', 'https://wikipedia.org', 'Content');
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('execute')->willReturnCallback(static fn (ToolCall $toolCall): ToolResult => new ToolResult($toolCall, 'Done', new SourceCollection([$source])));

        [, $sources] = $this->runRound(new ToolCallRound(new SequentialToolExecutor($toolbox)), [new ToolCall('id1', 'tool1')]);

        $this->assertSame([$source], $sources->all());
    }

    public function testExecutingWithoutToolboxIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Executing a tool call requires a toolbox.');

        $this->runRound(new ToolCallRound(null), [(new ToolCall('id1', 'tool1'))->suspend()->approve()]);
    }

    public function testRejectedToolCallIsAnsweredWithoutToolbox()
    {
        [$messages] = $this->runRound(new ToolCallRound(null), [(new ToolCall('id1', 'tool1'))->reject('Keep it.')]);

        $this->assertSame(['id1' => 'Keep it.'], $this->answers($messages));
    }

    public function testAssistantMessageCarriesTheToolCallsInTheirState()
    {
        $requested = new ToolCall('id1', 'tool1');
        $suspended = new ToolCall('id2', 'tool2');
        $message = new AssistantMessage(new Text('Checking'), $requested, $suspended);

        $withState = ToolCallRound::withToolCalls($message, [$requested, $suspended->suspend('Needs approval.')]);

        $this->assertTrue($withState->getId()->equals($message->getId()));
        $this->assertSame('Checking', $withState->asText());
        $this->assertSame([ToolCallState::Requested, ToolCallState::Suspended], array_map(static fn (ToolCall $toolCall): ToolCallState => $toolCall->getState(), $withState->getToolCalls()));
        $this->assertSame($message, ToolCallRound::withToolCalls($message, [$requested, $suspended]));
    }

    public function testInStateFiltersTheToolCalls()
    {
        $requested = new ToolCall('id1', 'tool1');
        $suspended = (new ToolCall('id2', 'tool2'))->suspend();
        $rejected = (new ToolCall('id3', 'tool3'))->reject();

        $this->assertSame([$suspended], ToolCallRound::inState([$requested, $suspended, $rejected], ToolCallState::Suspended));
        $this->assertSame([$requested, $rejected], ToolCallRound::inState([$requested, $suspended, $rejected], ToolCallState::Requested, ToolCallState::Rejected));
    }

    /**
     * @param list<array<string, mixed>> $executed collects the arguments of the executed tool calls
     */
    private function round(array &$executed): ToolCallRound
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('execute')->willReturnCallback(static function (ToolCall $toolCall) use (&$executed): ToolResult {
            $executed[] = $toolCall->getArguments();

            return new ToolResult($toolCall, $toolCall->getName().' done');
        });

        return new ToolCallRound(new SequentialToolExecutor($toolbox));
    }

    /**
     * @param list<ToolCall> $toolCalls
     *
     * @return array{MessageBag, SourceCollection, list<ToolResult>}
     */
    private function runRound(ToolCallRound $round, array $toolCalls): array
    {
        $generator = $round->run(new MessageBag(), new SourceCollection(), $toolCalls);
        iterator_to_array($generator, false);

        return $generator->getReturn();
    }

    /**
     * @return array<string, string|null> the answers keyed by tool call id
     */
    private function answers(MessageBag $messages): array
    {
        $answers = [];
        foreach ($messages->getMessages() as $message) {
            $this->assertInstanceOf(ToolCallMessage::class, $message);
            $answers[$message->getToolCall()->getId()] = $message->asText();
        }

        return $answers;
    }
}
