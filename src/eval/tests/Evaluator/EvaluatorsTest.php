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
use Symfony\AI\Eval\Evaluator\Faithfulness;
use Symfony\AI\Eval\Evaluator\JsonSchema;
use Symfony\AI\Eval\Evaluator\Limits;
use Symfony\AI\Eval\Evaluator\LlmJudge;
use Symfony\AI\Eval\Evaluator\Similarity;
use Symfony\AI\Eval\Evaluator\TextAssertions;
use Symfony\AI\Eval\Evaluator\ToolArguments;
use Symfony\AI\Eval\Evaluator\ToolsCalled;
use Symfony\AI\Eval\Evaluator\Verdict;
use Symfony\AI\Eval\Exception\RuntimeException;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\RecordedToolCall;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\Vector\Vector;

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
        $platform = MockPlatformFactory::createPlatform(static function ($model, array $payload, array $options) use (&$prompts): ResultInterface {
            $users = array_filter($payload['messages'], static fn (array $message): bool => 'user' === $message['role']);
            $prompts[] = end($users)['content'] ?? null;
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

    public function testLlmJudgeFailsWithoutScore()
    {
        $judge = new LlmJudge(MockPlatformFactory::createPlatform(static fn (): ResultInterface => new ObjectResult(new Verdict())), 'judge-model');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The judge model "judge-model" answered without a score.');

        $judge->evaluate($this->case(['judge' => ['criteria' => 'Mentions the ticket id.']]), $this->recordedRun('Done.'));
    }

    public function testLlmJudgeFailsWithoutVerdict()
    {
        $judge = new LlmJudge(MockPlatformFactory::createPlatform(static fn (): ResultInterface => new ObjectResult(['score' => 1.0])), 'judge-model');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('instead of a verdict');

        $judge->evaluate($this->case(['judge' => ['criteria' => 'Mentions the ticket id.']]), $this->recordedRun('Done.'));
    }

    public function testJsonSchema()
    {
        $evaluator = new JsonSchema();
        $schema = ['json_schema' => [
            'type' => 'object',
            'properties' => ['ticket' => ['type' => 'string', 'pattern' => '^RF-\d+$'], 'amount' => ['type' => 'number']],
            'required' => ['ticket'],
        ]];

        $this->assertNull($evaluator->evaluate($this->case([]), $this->recordedRun('{}')));
        $this->assertTrue($evaluator->evaluate($this->case($schema), $this->recordedRun('{"ticket": "RF-4711", "amount": 49.9}'))?->isPassed());

        $invalidJson = $evaluator->evaluate($this->case($schema), $this->recordedRun('Your ticket is RF-4711.'));
        $this->assertNotNull($invalidJson);
        $this->assertFalse($invalidJson->isPassed());
        $this->assertStringContainsString('not valid JSON', (string) $invalidJson->getExplanation());

        $mismatch = $evaluator->evaluate($this->case($schema), $this->recordedRun('{"ticket": "4711", "amount": "49.90"}'));
        $this->assertNotNull($mismatch);
        $this->assertFalse($mismatch->isPassed());
        $this->assertStringContainsString('/ticket', (string) $mismatch->getExplanation());
        $this->assertStringContainsString('/amount', (string) $mismatch->getExplanation());
    }

    public function testJsonSchemaValidatesStructuredOutput()
    {
        $evaluator = new JsonSchema();
        $case = $this->case(['json_schema' => ['type' => 'object', 'required' => ['reasoning', 'score']]]);

        $verdict = new Verdict();
        $verdict->score = 1.0;

        $this->assertTrue($evaluator->evaluate($case, new RecordedRun(new MessageBag(), new ObjectResult($verdict)))?->isPassed());
        $this->assertFalse($evaluator->evaluate($case, new RecordedRun(new MessageBag(), new ObjectResult(['score' => 1.0])))?->isPassed());
        $this->assertFalse($evaluator->evaluate($case, new RecordedRun(new MessageBag(), null))?->isPassed());
    }

    public function testFaithfulnessJudgesTheAnswerAgainstTheToolResults()
    {
        $calls = [];
        $platform = MockPlatformFactory::createPlatform(static function ($model, array $payload) use (&$calls): ResultInterface {
            $calls[] = $payload['messages'];
            $verdict = new Verdict();
            $verdict->reasoning = 'The refund amount is not in the tool results.';
            $verdict->score = 0.5;

            return new ObjectResult($verdict);
        });

        $evaluator = new Faithfulness($platform, 'judge-model');
        $run = new RecordedRun(new MessageBag(Message::ofUser('I want my money back.')), new TextResult('Ticket RF-4711 refunds 49.90 EUR.'), [
            new RecordedToolCall('open_refund', ['orderNumber' => 'SO-10023'], 'RF-4711'),
        ]);

        $score = $evaluator->evaluate($this->case([]), $run);
        $this->assertNotNull($score);
        $this->assertSame('faithfulness', $score->getEvaluator());
        $this->assertFalse($score->isPassed());
        $this->assertSame(0.5, $score->getValue());
        $this->assertSame('The refund amount is not in the tool results.', $score->getExplanation());

        $this->assertStringContainsString('check each one against the tool results', (string) $calls[0][0]['content']);
        $this->assertStringContainsString('Tool call: open_refund({"orderNumber":"SO-10023"}) => RF-4711', (string) $calls[0][1]['content']);
    }

    public function testFaithfulnessSkipsRunsWithoutRetrievedContext()
    {
        $evaluator = new Faithfulness(MockPlatformFactory::createPlatform(static fn (): never => throw new \LogicException('The judge must not be called.')), 'judge-model');

        $this->assertNull($evaluator->evaluate($this->case([]), $this->recordedRun('Done.')));
        $this->assertNull($evaluator->evaluate($this->case([]), new RecordedRun(new MessageBag(), new TextResult('Done.'), [new RecordedToolCall('order_lookup', [], 'Unknown order.', true)])));
        $this->assertNull($evaluator->evaluate($this->case([]), new RecordedRun(new MessageBag(), null, [new RecordedToolCall('order_lookup', [], 'delivered')])));
    }

    public function testSimilarity()
    {
        $embeddings = [
            'Your refund ticket is RF-4711.' => [1.0, 0.0],
            'I opened the refund ticket RF-4711.' => [0.9, 0.1],
            'The weather is nice.' => [0.0, 1.0],
        ];
        $platform = MockPlatformFactory::createPlatform(static fn ($model, string $payload): ResultInterface => new VectorResult([new Vector($embeddings[$payload])]));

        $evaluator = new Similarity($platform, 'embeddings-model');
        $case = $this->case(['answer' => 'Your refund ticket is RF-4711.']);

        $this->assertNull($evaluator->evaluate($this->case([]), $this->recordedRun('Done.')));

        $similar = $evaluator->evaluate($case, $this->recordedRun('I opened the refund ticket RF-4711.'));
        $this->assertNotNull($similar);
        $this->assertTrue($similar->isPassed());
        $this->assertEqualsWithDelta(0.9939, $similar->getValue(), 0.0001);
        $this->assertSame(['model' => 'embeddings-model'], $similar->getMetadata());

        $different = $evaluator->evaluate($case, $this->recordedRun('The weather is nice.'));
        $this->assertNotNull($different);
        $this->assertFalse($different->isPassed());
        $this->assertSame(0.0, $different->getValue());

        $this->assertFalse($evaluator->evaluate($case, new RecordedRun(new MessageBag(), null))?->isPassed());
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
