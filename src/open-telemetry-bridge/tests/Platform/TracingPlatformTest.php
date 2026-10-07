<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\Tests\Platform;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\OpenTelemetryBridge\Platform\TracingPlatform;
use Symfony\AI\OpenTelemetryBridge\Tests\InMemoryTracingTrait;
use Symfony\AI\OpenTelemetryBridge\UserIdResolverInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\Vector\Vector;

final class TracingPlatformTest extends TestCase
{
    use InMemoryTracingTrait;

    public function testChatSpanCarriesRequestAndResponseAttributes()
    {
        $platform = $this->platform(static function () {
            $result = new TextResult('Hello!');
            $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 12, completionTokens: 3, cacheReadTokens: 4, model: 'gpt-4.1-2025-04-14'));
            $result->getMetadata()->add('finish_reason', new FinishReason(FinishReasonCase::TOOL_CALL, 'tool_calls'));

            return $result;
        });

        $messages = new MessageBag(Message::forSystem('Be brief.'), Message::ofUser('Hi'));
        $result = $platform->invoke('gpt-4.1', $messages, ['temperature' => 0.2, 'max_output_tokens' => 100, 'stop' => ['END']]);

        $this->assertSame([], $this->exporter->getSpans(), 'The span stays open until the result is converted');
        $this->assertSame('Hello!', $result->asText());

        $span = $this->span('chat gpt-4.1');
        $attributes = $span->getAttributes();
        $this->assertSame(SpanKind::KIND_CLIENT, $span->getKind());
        $this->assertSame('chat', $attributes->get('gen_ai.operation.name'));
        $this->assertSame('mock', $attributes->get('gen_ai.provider.name'));
        $this->assertSame('gpt-4.1', $attributes->get('gen_ai.request.model'));
        $this->assertSame('gpt-4.1-2025-04-14', $attributes->get('gen_ai.response.model'));
        $this->assertSame(0.2, $attributes->get('gen_ai.request.temperature'));
        $this->assertSame(100, $attributes->get('gen_ai.request.max_tokens'));
        $this->assertSame(['END'], $attributes->get('gen_ai.request.stop_sequences'));
        $this->assertSame(12, $attributes->get('gen_ai.usage.input_tokens'));
        $this->assertSame(3, $attributes->get('gen_ai.usage.output_tokens'));
        $this->assertSame(4, $attributes->get('gen_ai.usage.cache_read.input_tokens'));
        $this->assertSame(['tool_call'], $attributes->get('gen_ai.response.finish_reasons'));
        $this->assertSame($messages->getId()->toRfc4122(), $attributes->get('gen_ai.conversation.id'));
        $this->assertSame('[{"type":"text","content":"Be brief."}]', $attributes->get('gen_ai.system_instructions'));
        $this->assertSame('[{"role":"user","parts":[{"type":"text","content":"Hi"}]}]', $attributes->get('gen_ai.input.messages'));
        $this->assertSame('[{"role":"assistant","parts":[{"type":"text","content":"Hello!"}],"finish_reason":"tool_call"}]', $attributes->get('gen_ai.output.messages'));
    }

    public function testContentIsNotCapturedByDefault()
    {
        $platform = new TracingPlatform(MockPlatformFactory::createPlatform('Hello!'), $this->tracer, 'mock');

        $platform->invoke('gpt-4.1', new MessageBag(Message::ofUser('Hi')))->asText();

        $attributes = $this->span('chat gpt-4.1')->getAttributes();
        $this->assertNull($attributes->get('gen_ai.input.messages'));
        $this->assertNull($attributes->get('gen_ai.system_instructions'));
        $this->assertNull($attributes->get('gen_ai.output.messages'));
    }

    public function testToolCallOutputIsCaptured()
    {
        $platform = $this->platform(static fn () => new ToolCallResult([new ToolCall('call_1', 'clock', ['timezone' => 'UTC'])]));

        $platform->invoke('gpt-4.1', new MessageBag(Message::ofUser('Time?')))->getResult();

        $this->assertSame(
            '[{"role":"assistant","parts":[{"type":"tool_call","id":"call_1","name":"clock","arguments":{"timezone":"UTC"}}]}]',
            $this->span('chat gpt-4.1')->getAttributes()->get('gen_ai.output.messages'),
        );
    }

    public function testEmbeddingsOperationIsDerivedFromTheResult()
    {
        $platform = $this->platform(static fn () => new VectorResult([new Vector([0.1, 0.2])]));

        $platform->invoke('text-embedding-3-small', 'Hello')->asVectors();

        $span = $this->span('embeddings text-embedding-3-small');
        $this->assertSame('embeddings', $span->getAttributes()->get('gen_ai.operation.name'));
        $this->assertNull($span->getAttributes()->get('gen_ai.output.messages'));
    }

    public function testProviderRequestNestsBelowTheInferenceSpan()
    {
        $platform = $this->platform(function () {
            $this->tracer->spanBuilder('HTTP POST')->startSpan()->end();

            return new TextResult('Hello!');
        });

        $platform->invoke('gpt-4.1', new MessageBag(Message::ofUser('Hi')))->asText();

        $this->assertSame(['HTTP POST'], $this->childNames($this->span('chat gpt-4.1')));
    }

    public function testStreamedSpanEndsAfterConsumptionWithUsageAndOutput()
    {
        $platform = $this->platform(static fn () => new StreamResult((static function () {
            yield new TextDelta('Hello ');
            yield new TextDelta('world');
            yield new TokenUsage(promptTokens: 7, completionTokens: 2);
        })()));

        foreach ($platform->invoke('gpt-4.1', new MessageBag(Message::ofUser('Hi')), ['stream' => true])->asStream() as $delta) {
            $this->assertSame([], $this->exporter->getSpans(), 'The span is still open while the stream is consumed');
        }

        $attributes = $this->span('chat gpt-4.1')->getAttributes();
        $this->assertSame(7, $attributes->get('gen_ai.usage.input_tokens'));
        $this->assertSame(2, $attributes->get('gen_ai.usage.output_tokens'));
        $this->assertStringContainsString('Hello world', (string) $attributes->get('gen_ai.output.messages'));
    }

    public function testAbandonedStreamStillEndsTheSpan()
    {
        $platform = $this->platform(static fn () => new StreamResult((static function () {
            yield new TextDelta('Hello ');
            yield new TextDelta('world');
        })()));

        $result = $platform->invoke('gpt-4.1', new MessageBag(Message::ofUser('Hi')), ['stream' => true]);
        foreach ($result->asStream() as $delta) {
            break;
        }
        unset($result, $delta);
        gc_collect_cycles();

        $this->assertCount(1, $this->exporter->getSpans());
    }

    public function testNeverConvertedResultStillEndsTheSpan()
    {
        $platform = $this->platform(static fn () => new TextResult('Hello!'));

        $platform->invoke('gpt-4.1', new MessageBag(Message::ofUser('Hi')));
        gc_collect_cycles();

        $this->assertCount(1, $this->exporter->getSpans());
    }

    public function testProviderErrorMarksTheSpanAndIsRethrownUnchanged()
    {
        $exception = new RuntimeException('Provider down');
        $platform = $this->platform(static fn () => throw $exception);

        try {
            $platform->invoke('gpt-4.1', new MessageBag(Message::ofUser('Hi')))->getResult();
            $this->fail('The exception must reach the caller');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $span = $this->span('chat gpt-4.1');
        $this->assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        $this->assertSame(RuntimeException::class, $span->getAttributes()->get('error.type'));
        $this->assertCount(1, $span->getEvents(), 'The exception is recorded');
    }

    public function testUserIdIsRecordedWhenResolved()
    {
        $platform = new TracingPlatform(MockPlatformFactory::createPlatform('Hello!'), $this->tracer, 'mock', userIdResolver: $this->userIdResolver('jane@example.com'));

        $platform->invoke('gpt-4.1', new MessageBag(Message::ofUser('Hi')))->asText();

        $this->assertSame('jane@example.com', $this->span('chat gpt-4.1')->getAttributes()->get('user.id'));
    }

    public function testUserIdIsOmittedWithoutUser()
    {
        $platform = new TracingPlatform(MockPlatformFactory::createPlatform('Hello!'), $this->tracer, 'mock', userIdResolver: $this->userIdResolver(null));

        $platform->invoke('gpt-4.1', new MessageBag(Message::ofUser('Hi')))->asText();

        $this->assertNull($this->span('chat gpt-4.1')->getAttributes()->get('user.id'));
    }

    public function testThrowingTracerDoesNotBreakTheCall()
    {
        $tracer = $this->createStub(TracerInterface::class);
        $tracer->method('spanBuilder')->willThrowException(new RuntimeException('Tracer broken'));

        $platform = new TracingPlatform(MockPlatformFactory::createPlatform('Still works'), $tracer, 'mock');

        $this->assertSame('Still works', $platform->invoke('gpt-4.1', new MessageBag(Message::ofUser('Hi')))->asText());
    }

    private function platform(\Closure $responses): TracingPlatform
    {
        return new TracingPlatform(MockPlatformFactory::createPlatform($responses), $this->tracer, 'mock', true);
    }

    private function userIdResolver(?string $userId): UserIdResolverInterface
    {
        return new class($userId) implements UserIdResolverInterface {
            public function __construct(
                private readonly ?string $userId,
            ) {
            }

            public function resolve(): ?string
            {
                return $this->userId;
            }
        };
    }
}
