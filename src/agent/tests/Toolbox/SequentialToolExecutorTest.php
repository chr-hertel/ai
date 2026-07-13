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
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Execution\InteractionReason;
use Symfony\AI\Agent\Execution\InteractionResponse;
use Symfony\AI\Agent\Execution\Update\Interaction;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Toolbox\Exception\ToolInteractionException;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

final class SequentialToolExecutorTest extends TestCase
{
    public function testExecutesToolCallsAndReturnsMessages()
    {
        $toolCall = new ToolCall('call_1', 'lookup', ['q' => 'foo']);
        $executor = new SequentialToolExecutor($this->toolbox(static fn (ToolCall $call): ToolResult => new ToolResult($call, 'found it')));

        $generator = $executor->execute(new ToolCallResult([$toolCall]), $this->agentContext());
        $updates = iterator_to_array($generator, false);

        $this->assertCount(1, $updates);
        $this->assertInstanceOf(Progress::class, $updates[0]);

        $messages = $generator->getReturn();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(AssistantMessage::class, $messages[0]);
        $this->assertInstanceOf(ToolCallMessage::class, $messages[1]);
        $this->assertSame('found it', $messages[1]->asText());
    }

    public function testToolInteractionExceptionPausesAndResponseBecomesToolResult()
    {
        $toolCall = new ToolCall('call_1', 'ask_user', ['question' => 'Which season?']);
        $executor = new SequentialToolExecutor($this->toolbox(static function (): never {
            throw ToolInteractionException::askUser('Which season?');
        }));

        $generator = $executor->execute(new ToolCallResult([$toolCall]), $this->agentContext());

        $this->assertInstanceOf(Progress::class, $generator->current());
        $generator->next();

        $interaction = $generator->current();
        $this->assertInstanceOf(Interaction::class, $interaction);
        $this->assertSame(InteractionReason::Input, $interaction->getReason());
        $this->assertSame('Which season?', $interaction->getPrompt());
        $this->assertSame($toolCall, $interaction->getToolCall());
        $this->assertCount(1, $interaction->getMessages());

        $generator->send(new InteractionResponse('Season 5'));
        self::drain($generator);

        $messages = $generator->getReturn();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(ToolCallMessage::class, $messages[1]);
        $this->assertSame('Season 5', $messages[1]->asText());
    }

    public function testMissingInteractionResponseYieldsFallbackToolResult()
    {
        $toolCall = new ToolCall('call_1', 'ask_user');
        $executor = new SequentialToolExecutor($this->toolbox(static function (): never {
            throw ToolInteractionException::askUser('Anyone there?');
        }));

        $generator = $executor->execute(new ToolCallResult([$toolCall]), $this->agentContext());
        iterator_to_array($generator, false);

        $messages = $generator->getReturn();
        $this->assertSame('The user did not provide a response.', $messages[1]->asText());
    }

    public function testApprovedToolExecutes()
    {
        $toolCall = new ToolCall('call_1', 'delete_record', ['id' => 42]);
        $executor = new SequentialToolExecutor(
            $this->toolbox(static fn (ToolCall $call): ToolResult => new ToolResult($call, 'deleted')),
            toolsRequiringApproval: ['delete_record'],
        );

        $generator = $executor->execute(new ToolCallResult([$toolCall]), $this->agentContext());

        $interaction = $generator->current();
        $this->assertInstanceOf(Interaction::class, $interaction);
        $this->assertSame(InteractionReason::ToolApproval, $interaction->getReason());
        $this->assertSame(['arguments' => ['id' => 42]], $interaction->getSchema());

        $generator->send(InteractionResponse::approve());
        self::drain($generator);

        $this->assertSame('deleted', $generator->getReturn()[1]->asText());
    }

    public function testDeniedToolIsNotExecuted()
    {
        $toolCall = new ToolCall('call_1', 'delete_record', ['id' => 42]);
        $executor = new SequentialToolExecutor(
            $this->toolbox(static function (): never {
                self::fail('The tool must not be executed when denied.');
            }),
            toolsRequiringApproval: ['delete_record'],
        );

        $generator = $executor->execute(new ToolCallResult([$toolCall]), $this->agentContext());

        $this->assertInstanceOf(Interaction::class, $generator->current());
        $generator->send(InteractionResponse::deny());
        self::drain($generator);

        $messages = $generator->getReturn();
        $this->assertSame('The tool call was denied by the user.', $messages[1]->asText());
    }

    private static function drain(\Generator $generator): void
    {
        while ($generator->valid()) {
            $generator->next();
        }
    }

    private function toolbox(\Closure $execute): ToolboxInterface
    {
        return new class($execute) implements ToolboxInterface {
            public function __construct(private readonly \Closure $execute)
            {
            }

            public function getTools(): array
            {
                return [];
            }

            public function execute(ToolCall $toolCall): ToolResult
            {
                return ($this->execute)($toolCall);
            }
        };
    }

    private function agentContext(): AgentContext
    {
        return new AgentContext($this->createMock(AgentInterface::class));
    }
}
