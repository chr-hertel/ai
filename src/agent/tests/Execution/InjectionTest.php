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
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Agent\Execution\PendingInjections;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class InjectionTest extends TestCase
{
    private ?ExecutionInterface $running = null;

    public function testMessagesAreTakenInOrder()
    {
        $injections = new PendingInjections();
        $this->assertTrue($injections->isEmpty());

        $this->assertTrue($injections->add('first'));
        $this->assertTrue($injections->add('second'));
        $this->assertFalse($injections->isEmpty());

        $drained = $injections->take();
        $this->assertSame(['first', 'second'], array_map(self::text(...), $drained));
        $this->assertTrue($injections->isEmpty());
    }

    public function testClosingRejectsMessagesAndReturnsTheRemainingOnes()
    {
        $injections = new PendingInjections();
        $injections->add('late');

        $this->assertSame(['late'], array_map(self::text(...), $injections->close()));
        $this->assertTrue($injections->isClosed());
        $this->assertFalse($injections->add('too late'));
    }

    public function testMessageInjectedDuringToolCallsReachesTheNextModelRequest()
    {
        $requests = [];
        $platform = MockPlatformFactory::createPlatform(static function ($model, $input) use (&$requests) {
            $requests[] = $input;

            return 1 === \count($requests)
                ? new ToolCallResult([new ToolCall('call_1', 'lookup')])
                : new TextResult('done');
        });

        $toolbox = $this->toolbox(function (): void {
            // the user types while the tool runs
            self::assertTrue($this->running?->inject('use the staging database'));
        });

        $agent = new Agent($platform, 'gpt-4', toolbox: $toolbox);
        $execution = $this->running = $agent->call(new MessageBag(Message::ofUser('check the users')));
        $this->assertInstanceOf(Execution::class, $execution);

        $stages = [];
        foreach ($execution as $update) {
            if ($update instanceof Progress) {
                $stages[] = $update->getStage();
            }
        }

        $this->assertSame('done', $execution->getResult()->getContent());
        $this->assertSame([Progress::STAGE_MODEL_REQUEST, Progress::STAGE_TOOL_CALL, Progress::STAGE_INJECTION, Progress::STAGE_MODEL_REQUEST], $stages);
        $this->assertCount(2, $requests);
        $this->assertSame(['user', 'assistant', 'tool', 'user'], self::roles($requests[1]));
        $this->assertSame(['user', 'assistant', 'tool', 'user', 'assistant'], self::roles($execution->getMessages()));
        $this->assertSame([], $execution->getPendingInjections());
    }

    public function testMessageInjectedDuringTheAnswerGetsAnotherRound()
    {
        $requests = [];
        $platform = MockPlatformFactory::createPlatform(function ($model, $input) use (&$requests) {
            $requests[] = $input;
            if (1 === \count($requests)) {
                // the user types while the model answers
                self::assertTrue($this->running?->inject('make it shorter'));

                return new TextResult('a long answer');
            }

            return new TextResult('short');
        });

        $agent = new Agent($platform, 'gpt-4');
        $execution = $this->running = $agent->call('explain fibers');

        $this->assertSame('short', $execution->getResult()->getContent());
        $this->assertCount(2, $requests);
        $this->assertSame(['user', 'assistant', 'user'], self::roles($requests[1]));
        $this->assertSame(['user', 'assistant', 'user', 'assistant'], self::roles($execution->getMessages()));
    }

    public function testInjectingIntoAFinishedExecutionIsRejected()
    {
        $agent = new Agent(MockPlatformFactory::createPlatform('hello'), 'gpt-4');
        $execution = $agent->call('hi');
        $execution->getResult();

        $this->assertFalse($execution->inject('anything else?'));
    }

    public function testInjectingIntoACanceledExecutionIsRejected()
    {
        $agent = new Agent(MockPlatformFactory::createPlatform('hello'), 'gpt-4');
        $execution = $agent->call('hi');
        $execution->cancel();

        $this->assertFalse($execution->inject('anything else?'));
    }

    public function testExecutionWithoutInjectionsRejectsMessages()
    {
        $execution = new Execution(static fn (): \Generator => yield from []);

        $this->assertFalse($execution->inject('ignored'));
    }

    private static function text(UserMessage $message): string
    {
        return $message->asText() ?? '';
    }

    /**
     * @param MessageBag|array<string, mixed>|list<array<string, mixed>> $messages the bag, or the payload the platform received
     *
     * @return list<string>
     */
    private static function roles(MessageBag|array $messages): array
    {
        if ($messages instanceof MessageBag) {
            return array_map(static fn (MessageInterface $message): string => $message->getRole()->value, $messages->getMessages());
        }

        return array_map(static fn (array $message): string => $message['role'], $messages['messages'] ?? $messages);
    }

    private function toolbox(\Closure $onExecute): ToolboxInterface
    {
        return new class($onExecute) implements ToolboxInterface {
            public function __construct(private readonly \Closure $onExecute)
            {
            }

            public function getTools(): array
            {
                return [new Tool(new ExecutionReference(self::class), 'lookup', 'Looks something up')];
            }

            public function execute(ToolCall $toolCall): ToolResult
            {
                ($this->onExecute)();

                return new ToolResult($toolCall, 'found it');
            }
        };
    }
}
