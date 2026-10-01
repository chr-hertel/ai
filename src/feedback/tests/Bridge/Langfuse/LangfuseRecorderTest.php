<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback\Tests\Bridge\Langfuse;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Feedback\Bridge\Langfuse\LangfuseRecorder;
use Symfony\AI\Feedback\Exception\RuntimeException;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\Feedback\Source;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class LangfuseRecorderTest extends TestCase
{
    public function testFeedbackIsSentAsScoreOnTheTrace()
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): JsonMockResponse {
            $requests[] = [$method, $url, $options];

            return new JsonMockResponse(['id' => 'score-1']);
        });

        $recorder = new LangfuseRecorder($client, 'https://cloud.langfuse.com/', 'pk', 'sk');
        $recorder->record(new Feedback('4bf92f3577b34da6a3ce929d0e0e4736', Signal::Thumbs, false, comment: 'Wrong order status.', messageId: 'msg-1'));

        $this->assertCount(1, $requests);
        [$method, $url, $options] = $requests[0];
        $this->assertSame('POST', $method);
        $this->assertSame('https://cloud.langfuse.com/api/public/scores', $url);
        $this->assertContains('Authorization: Basic '.base64_encode('pk:sk'), $options['headers']);

        $payload = json_decode($options['body'], true);
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $payload['traceId']);
        $this->assertSame('thumbs', $payload['name']);
        $this->assertSame(0, $payload['value']);
        $this->assertSame('BOOLEAN', $payload['dataType']);
        $this->assertSame('Wrong order status.', $payload['comment']);
        $this->assertSame('msg-1', $payload['metadata']['message_id']);
    }

    public function testDataTypes()
    {
        $recorder = new LangfuseRecorder(new MockHttpClient(), 'https://langfuse', 'pk', 'sk');

        $this->assertSame('NUMERIC', $recorder->payload(new Feedback('run', Signal::EvaluatorScore, 0.8, Source::Evaluator, 'llm_judge'))['dataType']);
        $this->assertSame('CATEGORICAL', $recorder->payload(new Feedback('run', Signal::Guardrail, 'denied'))['dataType']);
    }

    public function testRejectedScoreThrows()
    {
        $recorder = new LangfuseRecorder(new MockHttpClient(new JsonMockResponse(['message' => 'Invalid'], ['http_code' => 400])), 'https://langfuse', 'pk', 'sk');

        $this->expectException(RuntimeException::class);

        $recorder->record(new Feedback('run', Signal::Thumbs, true));
    }
}
