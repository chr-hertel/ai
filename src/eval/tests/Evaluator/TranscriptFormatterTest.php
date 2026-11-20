<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Evaluator;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Eval\Evaluator\TranscriptFormatter;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\RecordedToolCall;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

final class TranscriptFormatterTest extends TestCase
{
    public function testRendersConversationToolCallsAndAnswer()
    {
        $run = new RecordedRun(
            new MessageBag(
                Message::forSystem('You are the support assistant.'),
                Message::ofUser('Where is SO-1?'),
                Message::ofAssistant('Which order do you mean?'),
                Message::ofUser('SO-1, I want my money back.'),
            ),
            new TextResult('Your ticket is RF-1.'),
            [
                new RecordedToolCall('order_lookup', ['order' => 'SO-1'], ['status' => 'delivered']),
                new RecordedToolCall('open_refund', ['order' => 'SO-1'], 'RF-1'),
            ],
        );

        $this->assertSame(<<<'TRANSCRIPT'
            User: Where is SO-1?
            Assistant: Which order do you mean?
            User: SO-1, I want my money back.
            Tool call: order_lookup({"order":"SO-1"}) => {"status":"delivered"}
            Tool call: open_refund({"order":"SO-1"}) => RF-1
            Assistant (final answer): Your ticket is RF-1.
            TRANSCRIPT, TranscriptFormatter::format($run));
    }

    public function testRunWithoutAnswer()
    {
        $run = new RecordedRun(new MessageBag(Message::ofUser('Hi')), null);

        $this->assertSame("User: Hi\nAssistant (final answer): (no answer)", TranscriptFormatter::format($run));
    }
}
