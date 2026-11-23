<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Execution\Turn;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

final class RecordedRunTest extends TestCase
{
    public function testFromOutputTakesTheToolCallsAndTheDurationFromTheTurns()
    {
        $lookup = new ToolCall('call-1', 'order_lookup', ['order' => 'SO-1']);
        $refund = new ToolCall('call-2', 'open_refund', ['order' => 'SO-1']);
        $messages = new MessageBag(Message::ofUser('I want my money back.'));

        $result = new TextResult('Your ticket is RF-1.');
        $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 10, completionTokens: 5, totalTokens: 15));
        $turns = [
            new Turn('model', new ToolCallResult([$lookup, $refund]), [
                new ToolResult($lookup, 'delivered'),
                new ToolResult($refund, 'Refunds are closed.', failure: ToolNotFoundException::notFoundForToolCall($refund)),
            ], 1.0),
            new Turn('model', $result, duration: 0.5),
        ];

        $run = RecordedRun::fromOutput(new Output('model', $result, $messages, [], new RunContext('run-1'), $turns));

        $this->assertSame(['order_lookup', 'open_refund'], $run->toolSequence());
        $this->assertSame(['order' => 'SO-1'], $run->getToolCalls()[0]->getArguments());
        $this->assertSame('delivered', $run->getToolCalls()[0]->getResult());
        $this->assertFalse($run->getToolCalls()[0]->isFailed());
        $this->assertTrue($run->getToolCalls()[1]->isFailed());
        $this->assertSame($turns, $run->getTurns());
        $this->assertSame('Your ticket is RF-1.', $run->getAnswer());
        $this->assertSame(15, $run->getTokenUsage()?->getTotalTokens());
        $this->assertSame(1.5, $run->getDuration());
        $this->assertSame('run-1', $run->getRunId());
        $this->assertSame($messages, $run->getInput());
    }
}
