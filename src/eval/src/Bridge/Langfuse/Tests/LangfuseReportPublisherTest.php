<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Bridge\Langfuse\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Eval\Bridge\Langfuse\LangfuseReportPublisher;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\Exception\RuntimeException;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\Report\CaseResult;
use Symfony\AI\Eval\Report\Report;
use Symfony\AI\Eval\Score;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class LangfuseReportPublisherTest extends TestCase
{
    public function testPushesTheResultsAsExperiment()
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): JsonMockResponse {
            $requests[] = ['url' => $url, 'headers' => $options['headers'], 'body' => json_decode($options['body'], true)];

            return str_contains($url, '/otel/') ? new JsonMockResponse([]) : new JsonMockResponse(['message' => 'Accepted'], ['http_code' => 202]);
        });

        $refund = new EvalCase('refund-001', [['user' => 'I want my money back.']], expectations: ['contains' => ['RF-']], metadata: ['langfuse_item_id' => 'item-1', 'langfuse_dataset_id' => 'ds-1']);
        $report = new Report('support', [
            new CaseResult('candidate', $refund, 1, $this->recordedRun('Your refund is RF-4711.', '4bf92f3577b34da6a3ce929d0e0e4736'), [new Score('text_assertions', 1.0, true, metadata: ['model' => 'judge'])]),
        ]);

        (new LangfuseReportPublisher($client, 'https://langfuse/', 'pk', 'sk'))->publish($report);

        $this->assertCount(2, $requests);
        $this->assertSame('https://langfuse/api/public/otel/v1/traces', $requests[0]['url']);
        $this->assertContains('x-langfuse-ingestion-version: 4', $requests[0]['headers']);

        $resourceSpans = $requests[0]['body']['resourceSpans'][0];
        $this->assertSame([['key' => 'service.name', 'value' => ['stringValue' => 'symfony-ai-eval']]], $resourceSpans['resource']['attributes']);
        $span = $resourceSpans['scopeSpans'][0]['spans'][0];
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $span['traceId']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $span['spanId']);
        $this->assertSame('refund-001', $span['name']);
        $this->assertSame(1500000000, (int) $span['endTimeUnixNano'] - (int) $span['startTimeUnixNano']);
        $this->assertArrayNotHasKey('status', $span);

        $attributes = array_column(array_map(static fn (array $attribute): array => [$attribute['key'], $attribute['value']['stringValue']], $span['attributes']), 1, 0);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $attributes['langfuse.experiment.id']);
        $this->assertMatchesRegularExpression('/^support candidate \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $attributes['langfuse.experiment.name']);
        $this->assertSame('ds-1', $attributes['langfuse.experiment.dataset.id']);
        $this->assertSame('candidate', $attributes['langfuse.experiment.metadata.variant']);
        $this->assertSame('item-1', $attributes['langfuse.experiment.item.id']);
        $this->assertSame($span['spanId'], $attributes['langfuse.experiment.item.root_observation_id']);
        $this->assertSame('[{"user":"I want my money back."}]', $attributes['langfuse.observation.input']);
        $this->assertSame('Your refund is RF-4711.', $attributes['langfuse.observation.output']);
        $this->assertSame('{"contains":["RF-"]}', $attributes['langfuse.experiment.item.expected_output']);
        $this->assertSame('refund-001', $attributes['langfuse.experiment.item.metadata.case_id']);
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $attributes['langfuse.experiment.item.metadata.trace_id']);

        $this->assertSame('https://langfuse/api/public/scores', $requests[1]['url']);
        [$passed, $textAssertions] = $requests[1]['body'];
        $this->assertSame(['traceId' => $span['traceId'], 'observationId' => $span['spanId'], 'name' => 'passed', 'value' => 1, 'dataType' => 'BOOLEAN'], array_diff_key($passed, ['id' => true]));
        $this->assertSame('text_assertions', $textAssertions['name']);
        $this->assertSame(1.0, $textAssertions['value']);
        $this->assertSame('NUMERIC', $textAssertions['dataType']);
        $this->assertSame(['model' => 'judge', 'passed' => true], $textAssertions['metadata']);
    }

    public function testSplitsVariantsAndRepetitionsIntoExperiments()
    {
        $spans = [];
        $scores = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$spans, &$scores): JsonMockResponse {
            if (str_contains($url, '/otel/')) {
                $spans = json_decode($options['body'], true)['resourceSpans'][0]['scopeSpans'][0]['spans'];

                return new JsonMockResponse([]);
            }

            $scores = json_decode($options['body'], true);

            return new JsonMockResponse(['message' => 'Accepted'], ['http_code' => 202]);
        });

        $local = new EvalCase('hello', 'Hi');
        $failed = new RecordedRun(new MessageBag(Message::ofUser('Hi')), null, error: new \LogicException('The model is down.'));
        $report = new Report('greetings', [
            new CaseResult('baseline', $local, 1, $this->recordedRun('Hello!'), [Score::fail('text_assertions', 'Missing "hello".')]),
            new CaseResult('baseline', $local, 2, $failed, []),
            new CaseResult('candidate', $local, 1, $this->recordedRun('Hello!'), []),
        ]);

        (new LangfuseReportPublisher($client, 'https://langfuse', 'pk', 'sk'))->publish($report);

        $attributes = array_map(static fn (array $span): array => array_column(array_map(static fn (array $attribute): array => [$attribute['key'], $attribute['value']['stringValue']], $span['attributes']), 1, 0), $spans);
        $this->assertCount(3, array_unique(array_column($attributes, 'langfuse.experiment.id')));
        $this->assertStringEndsWith(' #1', $attributes[0]['langfuse.experiment.name']);
        $this->assertStringEndsWith(' #2', $attributes[1]['langfuse.experiment.name']);
        $this->assertSame(['hello', 'hello', 'hello'], array_column($attributes, 'langfuse.experiment.item.id'));
        $this->assertArrayNotHasKey('langfuse.experiment.dataset.id', $attributes[0]);
        $this->assertArrayNotHasKey('langfuse.observation.output', $attributes[1]);
        $this->assertSame(['code' => 2, 'message' => 'LogicException: The model is down.'], $spans[1]['status']);

        $this->assertSame(['passed', 'text_assertions', 'passed', 'passed'], array_column($scores, 'name'));
        $this->assertSame(0, $scores[0]['value']);
        $this->assertSame('[text_assertions] Missing "hello".', $scores[0]['comment']);
        $this->assertSame('LogicException: The model is down.', $scores[2]['comment']);
    }

    public function testRejectedScoresFail()
    {
        $client = new MockHttpClient([
            new JsonMockResponse([]),
            new JsonMockResponse(['accepted' => 0, 'rejected' => 1, 'errors' => [['message' => 'Invalid value']]], ['http_code' => 207]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Langfuse rejected experiment scores');

        (new LangfuseReportPublisher($client, 'https://langfuse', 'pk', 'sk'))->publish(new Report('greetings', [new CaseResult('baseline', new EvalCase('hello', 'Hi'), 1, $this->recordedRun('Hello!'), [])]));
    }

    public function testRejectedSpansFail()
    {
        $client = new MockHttpClient(new JsonMockResponse(['partialSuccess' => ['rejectedSpans' => '1', 'errorMessage' => 'Invalid span']]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Langfuse rejected 1 experiment items: "Invalid span"');

        (new LangfuseReportPublisher($client, 'https://langfuse', 'pk', 'sk'))->publish(new Report('greetings', [new CaseResult('baseline', new EvalCase('hello', 'Hi'), 1, $this->recordedRun('Hello!'), [])]));
    }

    private function recordedRun(string $answer, ?string $traceId = null): RecordedRun
    {
        return new RecordedRun(new MessageBag(Message::ofUser('Hi')), new TextResult($answer), duration: 1.5, runContext: new RunContext('run-1', traceId: $traceId));
    }
}
