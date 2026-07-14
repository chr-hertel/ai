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
use Symfony\AI\Agent\Exception\InteractionRequiredException;
use Symfony\AI\Agent\Execution\InteractionReason;
use Symfony\AI\Agent\Execution\InteractionResponse;
use Symfony\AI\Agent\Execution\Update\Interaction;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Exception\ToolInteractionException;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;

final class InteractionTest extends TestCase
{
    public function testAToolCanPauseTheExecutionToAskAHuman()
    {
        $agent = $this->agentWithTool(new AskingTool());

        $asked = null;
        $result = $agent->call('Book it.')
            ->onInteraction(static function (Interaction $interaction) use (&$asked): InteractionResponse {
                $asked = $interaction;

                return new InteractionResponse('Window seat, please.');
            })
            ->getResult();

        $this->assertInstanceOf(Interaction::class, $asked);
        $this->assertSame(InteractionReason::Input, $asked->getReason());
        $this->assertSame('Which seat do you want?', $asked->getPrompt());
        $this->assertSame('Booked.', $result->getContent());
    }

    public function testTheHumanAnswerBecomesTheToolResult()
    {
        $seen = [];
        $platform = new InMemoryPlatform(static function (mixed $model, mixed $input) use (&$seen): ResultInterface {
            $seen[] = $input;

            return 1 === \count($seen)
                ? new ToolCallResult([new ToolCall('id1', 'ask_user', [])])
                : new TextResult('Booked.');
        });

        $agent = $this->agentWithTool(new AskingTool(), $platform);

        $agent->call('Book it.')
            ->onInteraction(static fn (): InteractionResponse => new InteractionResponse('Window seat, please.'))
            ->getResult();

        // the second invocation carries the tool call message holding the human's answer
        $toolMessages = array_filter($seen[1]->getMessages(), static fn (object $m): bool => method_exists($m, 'getToolCall'));
        $this->assertCount(1, $toolMessages);
        $this->assertSame('Window seat, please.', reset($toolMessages)->asText());
    }

    public function testAwaitThrowsWhenNobodyHandlesTheInteraction()
    {
        $agent = $this->agentWithTool(new AskingTool());

        $this->expectException(InteractionRequiredException::class);

        $agent->call('Book it.')->getResult();
    }

    public function testTheInteractionCarriesTheFullConversationForResuming()
    {
        $agent = $this->agentWithTool(new AskingTool());

        $captured = null;
        $agent->call('Book it.')
            ->onInteraction(static function (Interaction $interaction) use (&$captured): InteractionResponse {
                $captured = $interaction;

                return new InteractionResponse('Window seat, please.');
            })
            ->getResult();

        $this->assertInstanceOf(Interaction::class, $captured);
        $this->assertInstanceOf(ToolCall::class, $captured->getToolCall());
        $this->assertSame('ask_user', $captured->getToolCall()->getName());

        // the user message plus the assistant message holding the pending tool call
        $this->assertCount(2, $captured->getMessages());
    }

    public function testAToolCanBeGatedBehindHumanApproval()
    {
        $tool = new DeletingTool();
        $toolbox = new Toolbox([$tool]);
        $agent = new Agent(
            $this->platformCallingTool('delete_everything'),
            'gpt-4o',
            toolbox: $toolbox,
            toolExecutor: new SequentialToolExecutor($toolbox, toolsRequiringApproval: ['delete_everything']),
        );

        $asked = null;
        $agent->call('Delete it all.')
            ->onInteraction(static function (Interaction $interaction) use (&$asked): InteractionResponse {
                $asked = $interaction;

                return InteractionResponse::deny();
            })
            ->getResult();

        $this->assertInstanceOf(Interaction::class, $asked);
        $this->assertSame(InteractionReason::ToolApproval, $asked->getReason());
        $this->assertFalse($tool->executed, 'A denied tool call must not execute.');
    }

    public function testAnApprovedToolCallExecutes()
    {
        $tool = new DeletingTool();
        $toolbox = new Toolbox([$tool]);
        $agent = new Agent(
            $this->platformCallingTool('delete_everything'),
            'gpt-4o',
            toolbox: $toolbox,
            toolExecutor: new SequentialToolExecutor($toolbox, toolsRequiringApproval: ['delete_everything']),
        );

        $agent->call('Delete it all.')
            ->onInteraction(static fn (): InteractionResponse => InteractionResponse::approve())
            ->getResult();

        $this->assertTrue($tool->executed);
    }

    private function agentWithTool(object $tool, ?InMemoryPlatform $platform = null): Agent
    {
        $toolbox = new Toolbox([$tool]);

        return new Agent($platform ?? $this->platformCallingTool('ask_user'), 'gpt-4o', toolbox: $toolbox);
    }

    private function platformCallingTool(string $toolName): InMemoryPlatform
    {
        $invocation = 0;

        return new InMemoryPlatform(static function () use (&$invocation, $toolName): ResultInterface {
            return 0 === $invocation++
                ? new ToolCallResult([new ToolCall('id1', $toolName, [])])
                : new TextResult('Booked.');
        });
    }
}

#[AsTool('ask_user', 'Asks the user which seat they want')]
final class AskingTool
{
    public function __invoke(): string
    {
        throw ToolInteractionException::askUser('Which seat do you want?');
    }
}

#[AsTool('delete_everything', 'Deletes everything')]
final class DeletingTool
{
    public bool $executed = false;

    public function __invoke(): string
    {
        $this->executed = true;

        return 'Deleted.';
    }
}
