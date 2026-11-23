<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback\Bridge\Phoenix\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Feedback\Bridge\Phoenix\PhoenixRecorder;
use Symfony\AI\Feedback\Exception\InvalidArgumentException;
use Symfony\AI\Feedback\Exception\RuntimeException;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\Feedback\Source;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class PhoenixRecorderTest extends TestCase
{
    private const TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    public function testFeedbackIsSentAsAnnotationOnTheTrace()
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): JsonMockResponse {
            $requests[] = [$method, $url, $options];

            return new JsonMockResponse(['data' => [['id' => 'VHJhY2VBbm5vdGF0aW9uOjE=']]]);
        });

        $recorder = new PhoenixRecorder($client, 'https://phoenix.example.com/', 'secret');
        $recorder->record(new Feedback('run-1', Signal::Thumbs, false, messageId: 'msg-1', comment: 'Wrong order status.', metadata: ['user' => 'jane'], traceId: self::TRACE_ID, id: 'client-feedback-1'));

        $this->assertCount(1, $requests);
        [$method, $url, $options] = $requests[0];
        $this->assertSame('POST', $method);
        $this->assertSame('https://phoenix.example.com/v1/trace_annotations', $url);
        $this->assertContains('Authorization: Bearer secret', $options['headers']);

        $this->assertSame(['data' => [[
            'trace_id' => self::TRACE_ID,
            'name' => 'thumbs',
            'annotator_kind' => 'HUMAN',
            'identifier' => 'client-feedback-1',
            'result' => ['score' => 0, 'explanation' => 'Wrong order status.'],
            'metadata' => ['user' => 'jane', 'run_id' => 'run-1', 'signal' => 'thumbs', 'source' => 'user', 'message_id' => 'msg-1'],
        ]]], json_decode($options['body'], true));
    }

    public function testWithoutApiKeyNoAuthorizationIsSent()
    {
        $headers = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$headers): JsonMockResponse {
            $headers = $options['headers'];

            return new JsonMockResponse(['data' => []]);
        });

        $recorder = new PhoenixRecorder($client);
        $recorder->record(new Feedback('run-1', Signal::Thumbs, true, traceId: self::TRACE_ID));

        $this->assertIsArray($headers);
        foreach ($headers as $header) {
            $this->assertStringStartsNotWith('Authorization:', $header);
        }
    }

    public function testResults()
    {
        $this->assertSame(['score' => 0.8], $this->annotation(new Feedback('run', Signal::EvaluatorScore, 0.8, Source::Evaluator, 'llm_judge', traceId: self::TRACE_ID))['result']);
        $this->assertSame(['label' => 'denied'], $this->annotation(new Feedback('run', Signal::Guardrail, 'denied', Source::Application, traceId: self::TRACE_ID))['result']);
        $this->assertSame(['score' => 1], $this->annotation(new Feedback('run', Signal::Regenerated, traceId: self::TRACE_ID))['result']);
    }

    public function testAnnotatorKinds()
    {
        $this->assertSame('HUMAN', $this->annotation(new Feedback('run', Signal::Correction, 'fixed', Source::Annotator, traceId: self::TRACE_ID))['annotator_kind']);
        $this->assertSame('CODE', $this->annotation(new Feedback('run', Signal::ToolError, true, Source::Application, traceId: self::TRACE_ID))['annotator_kind']);
        $this->assertSame('CODE', $this->annotation(new Feedback('run', Signal::EvaluatorScore, 1.0, Source::Evaluator, traceId: self::TRACE_ID))['annotator_kind']);
    }

    public function testIdentifierIsStableAcrossRetries()
    {
        $createdAt = new \DateTimeImmutable('2026-11-16T10:00:00.123+00:00');

        $first = $this->annotation(new Feedback('run', Signal::Thumbs, true, createdAt: $createdAt, traceId: self::TRACE_ID));
        $retry = $this->annotation(new Feedback('run', Signal::Thumbs, true, createdAt: $createdAt, traceId: self::TRACE_ID));

        $this->assertSame($first['identifier'], $retry['identifier']);
    }

    public function testFeedbackWithoutTraceIdIsRejected()
    {
        $recorder = new PhoenixRecorder(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);

        $recorder->record(new Feedback('0199a1c4-0000-7000-8000-000000000000', Signal::Thumbs, true));
    }

    public function testRejectedAnnotationThrows()
    {
        $recorder = new PhoenixRecorder(new MockHttpClient(new JsonMockResponse(['detail' => 'Invalid'], ['http_code' => 422])));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('status 422');

        $recorder->record(new Feedback('run', Signal::Thumbs, true, traceId: self::TRACE_ID));
    }

    /**
     * @return array<string, mixed>
     */
    private function annotation(Feedback $feedback): array
    {
        $body = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = $options['body'];

            return new JsonMockResponse(['data' => []]);
        });

        (new PhoenixRecorder($client))->record($feedback);

        return json_decode($body, true)['data'][0];
    }
}
