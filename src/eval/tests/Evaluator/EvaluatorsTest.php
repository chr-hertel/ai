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
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\Evaluator\Limits;
use Symfony\AI\Eval\Evaluator\LlmJudge;
use Symfony\AI\Eval\Evaluator\TextAssertions;
use Symfony\AI\Eval\Evaluator\ToolArguments;
use Symfony\AI\Eval\Evaluator\ToolsCalled;
use Symfony\AI\Eval\Evaluator\Verdict;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\RecordedToolCall;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

final class EvaluatorsTest extends TestCase
{
    public function testToolsCalled()
    {
        $run = $this->recordedRun('Done.', 'order_lookup', 'open_refund');
        $evaluator = new ToolsCalled();

        $this->assertNull($evaluator->evaluate($this->case([]), $run));
        $this->assertTrue($evaluator->evaluate($this->case(['tools_called' => ['open_refund']]), $run)?->isPassed());
        $this->assertTrue($evaluator->evaluate($this->case(['tools_called' => ['order_lookup', 'open_refund'], 'tool_order' => 'strict']), $run)?->isPassed());
        $this->assertFalse($evaluator->evaluate($this->case(['tools_called' => ['open_refund', 'order_lookup'], 'tool_order' => 'strict']), $run)?->isPassed());
        $this->assertFalse($evaluator->evaluate($this->case(['tools_called' => []]), $run)?->isPassed());

        $partial = $evaluator->evaluate($this->case(['tools_called' => ['order_lookup', 'escalate']]), $run);
        $this->assertNotNull($partial);
        $this->assertSame(0.5, $partial->getValue());
        $this->assertStringContainsString('escalate', (string) $partial->getExplanation());
    }

    public function testToolArguments()
    {
        $run = new RecordedRun(new MessageBag(), new TextResult('Done.'), [new RecordedToolCall('open_refund', ['orderNumber' => 'SO-10023', 'reason' => 'too small'])]);
        $evaluator = new ToolArguments();

        $this->assertTrue($evaluator->evaluate($this->case(['tool_arguments' => ['open_refund' => ['orderNumber' => 'SO-10023']]]), $run)?->isPassed());
        $this->assertTrue($evaluator->evaluate($this->case(['tool_arguments' => ['open_refund' => ['orderNumber' => '/^SO-\d+$/']]]), $run)?->isPassed());
        $this->assertFalse($evaluator->evaluate($this->case(['tool_arguments' => ['open_refund' => ['orderNumber' => 'SO-1']]]), $run)?->isPassed());
        $this->assertFalse($evaluator->evaluate($this->case(['tool_arguments' => ['order_lookup' => []]]), $run)?->isPassed());
    }

    public function testTextAssertions()
    {
        $run = $this->recordedRun('Your ticket is RF-4711.');
        $evaluator = new TextAssertions();

        $this->assertTrue($evaluator->evaluate($this->case(['contains' => ['TICKET'], 'not_contains' => ['processed'], 'matches' => '/RF-\d+/']), $run)?->isPassed());

        $score = $evaluator->evaluate($this->case(['contains' => ['refund', 'ticket']]), $run);
        $this->assertNotNull($score);
        $this->assertFalse($score->isPassed());
        $this->assertSame(0.5, $score->getValue());
    }

    public function testLimits()
    {
        $run = new RecordedRun(new MessageBag(), new TextResult('Done.'), [new RecordedToolCall('a', []), new RecordedToolCall('b', [])], [], new TokenUsage(totalTokens: 500), 2.5);
        $evaluator = new Limits();

        $this->assertTrue($evaluator->evaluate($this->case(['max_tool_calls' => 2, 'max_tokens' => 500, 'max_latency' => 3]), $run)?->isPassed());

        $score = $evaluator->evaluate($this->case(['max_tool_calls' => 1, 'max_latency' => 1]), $run);
        $this->assertNotNull($score);
        $this->assertFalse($score->isPassed());
        $this->assertStringContainsString('2 tool calls', (string) $score->getExplanation());
    }

    public function testLlmJudgeGradesTheTranscriptAgainstTheCriteria()
    {
        $prompts = [];
        $platform = new InMemoryPlatform(static function ($model, MessageBag $messages, array $options) use (&$prompts): ResultInterface {
            $prompts[] = $messages->getUserMessage()?->asText();
            $verdict = new Verdict();
            $verdict->reasoning = 'Mentions the ticket id.';
            $verdict->score = Verdict::class === $options['response_format'] ? 0.9 : 0.0;

            return new ObjectResult($verdict);
        });

        $judge = new LlmJudge($platform, 'judge-model');
        $case = $this->case(['judge' => ['criteria' => 'Mentions the ticket id.']]);

        $this->assertNull($judge->evaluate($this->case([]), $this->recordedRun('Done.')));

        $score = $judge->evaluate($case, $this->recordedRun('Your ticket is RF-4711.', 'open_refund'));
        $this->assertNotNull($score);
        $this->assertTrue($score->isPassed());
        $this->assertSame(0.9, $score->getValue());
        $this->assertSame('Mentions the ticket id.', $score->getExplanation());
        $this->assertStringContainsString('Tool call: open_refund', (string) $prompts[0]);
        $this->assertStringContainsString('Assistant (final answer): Your ticket is RF-4711.', (string) $prompts[0]);
    }

    /**
     * @param array<string, mixed> $expectations
     */
    private function case(array $expectations): EvalCase
    {
        return new EvalCase('case', 'I want my money back.', expectations: $expectations);
    }

    private function recordedRun(string $answer, string ...$tools): RecordedRun
    {
        return new RecordedRun(
            new MessageBag(Message::ofUser('I want my money back.')),
            new TextResult($answer),
            array_map(static fn (string $tool): RecordedToolCall => new RecordedToolCall($tool, []), array_values($tools)),
        );
    }
}
