<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\Tests\Agent;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\OpenTelemetryBridge\Agent\TracingAgent;
use Symfony\AI\OpenTelemetryBridge\Platform\TracingPlatform;
use Symfony\AI\OpenTelemetryBridge\Tests\InMemoryTracingTrait;
use Symfony\AI\OpenTelemetryBridge\Toolbox\TracingToolbox;
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
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

final class TracingAgentTest extends TestCase
{
    use InMemoryTracingTrait;

    public function testAgentRunWithToolCallProducesTheSpanTree()
    {
        $calls = 0;
        $platform = new TracingPlatform(MockPlatformFactory::createPlatform(static function () use (&$calls) {
            if (0 === $calls++) {
                $result = new ToolCallResult([new ToolCall('call_1', 'clock')]);
                $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 10, completionTokens: 5));

                return $result;
            }

            $result = new TextResult('It is noon.');
            $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 20, completionTokens: 3));

            return $result;
        }), $this->tracer, 'mock');
        $toolbox = new TracingToolbox(new Toolbox([new Clock()]), $this->tracer);
        $agent = new TracingAgent(new Agent($platform, 'gpt-4.1', name: 'support', toolbox: $toolbox), $this->tracer, true);

        $messages = new MessageBag(Message::ofUser('What time is it?'));
        $execution = $agent->call($messages);

        $this->assertSame([], $this->exporter->getSpans(), 'The execution is lazy, nothing runs before it is consumed');
        $this->assertSame('It is noon.', $execution->getResult()->getContent());

        $agentSpan = $this->span('invoke_agent support');
        $this->assertSame(['chat gpt-4.1', 'execute_tool clock', 'chat gpt-4.1'], $this->childNames($agentSpan));
        $this->assertSame(SpanKind::KIND_INTERNAL, $agentSpan->getKind());
        $this->assertSame('invoke_agent', $agentSpan->getAttributes()->get('gen_ai.operation.name'));
        $this->assertSame('support', $agentSpan->getAttributes()->get('gen_ai.agent.name'));
        $this->assertSame($messages->getId()->toRfc4122(), $agentSpan->getAttributes()->get('gen_ai.conversation.id'));
        $this->assertNull($agentSpan->getAttributes()->get('gen_ai.usage.input_tokens'), 'Usage stays on the inference spans');
        $this->assertSame('[{"role":"user","parts":[{"type":"text","content":"What time is it?"}]}]', $agentSpan->getAttributes()->get('gen_ai.input.messages'));
        $this->assertSame('[{"role":"assistant","parts":[{"type":"text","content":"It is noon."}]}]', $agentSpan->getAttributes()->get('gen_ai.output.messages'));
        $this->assertSame('What time is it?', $agentSpan->getAttributes()->get('input.value'));
        $this->assertSame('It is noon.', $agentSpan->getAttributes()->get('output.value'));

        [$first, $second] = $this->spansNamed('chat gpt-4.1');
        $this->assertSame(10, $first->getAttributes()->get('gen_ai.usage.input_tokens'));
        $this->assertSame(20, $second->getAttributes()->get('gen_ai.usage.input_tokens'));
    }

    public function testOutputCarriesTheFinishReasonOfTheFinalResult()
    {
        $platform = MockPlatformFactory::createPlatform(static function () {
            $result = new TextResult('Hello!');
            $result->getMetadata()->add('finish_reason', new FinishReason(FinishReasonCase::STOP, 'stop'));

            return $result;
        });
        $agent = new TracingAgent(new Agent($platform, 'gpt-4.1', name: 'support'), $this->tracer, true);

        $agent->call(new MessageBag(Message::ofUser('Hi'), Message::ofAssistant('Hello'), Message::ofUser('Bye')))->getResult();

        $attributes = $this->span('invoke_agent support')->getAttributes();
        $this->assertSame('[{"role":"assistant","parts":[{"type":"text","content":"Hello!"}],"finish_reason":"stop"}]', $attributes->get('gen_ai.output.messages'));
        $this->assertSame('Bye', $attributes->get('input.value'), 'The latest user message is the input of the turn');
    }

    public function testContentIsNotCapturedByDefault()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hello!'), 'gpt-4.1', name: 'support'), $this->tracer);

        $agent->call(new MessageBag(Message::ofUser('Hi')))->getResult();

        $attributes = $this->span('invoke_agent support')->getAttributes();
        $this->assertNull($attributes->get('gen_ai.input.messages'));
        $this->assertNull($attributes->get('gen_ai.output.messages'));
        $this->assertNull($attributes->get('input.value'));
        $this->assertNull($attributes->get('output.value'));
    }

    public function testSpanIsNotActiveWhileTheConsumerHandlesUpdates()
    {
        $platform = MockPlatformFactory::createPlatform(static fn () => new StreamResult((static function () {
            yield new TextDelta('Hello ');
            yield new TextDelta('world');
        })()));
        $agent = new TracingAgent(new Agent($platform, 'gpt-4.1', name: 'support'), $this->tracer);

        foreach ($agent->call(new MessageBag(Message::ofUser('Hi')), ['stream' => true])->getContent() as $delta) {
            $this->tracer->spanBuilder('consumer work')->startSpan()->end();
        }

        $this->assertSame(['invoke_agent support', 'consumer work', 'consumer work'], array_map(static fn ($span) => $span->getName(), $this->spans()));
        $this->assertSame([], $this->childNames($this->span('invoke_agent support')));
    }

    public function testAbandonedExecutionStillEndsTheSpan()
    {
        $platform = MockPlatformFactory::createPlatform(static fn () => new StreamResult((static function () {
            yield new TextDelta('Hello ');
            yield new TextDelta('world');
        })()));
        $agent = new TracingAgent(new Agent($platform, 'gpt-4.1', name: 'support'), $this->tracer);

        $execution = $agent->call(new MessageBag(Message::ofUser('Hi')), ['stream' => true]);
        foreach ($execution->getContent() as $delta) {
            break;
        }
        unset($execution, $delta);
        gc_collect_cycles();

        $this->assertCount(1, $this->spansNamed('invoke_agent support'));
    }

    public function testFailingRunMarksTheSpanAndIsRethrownUnchanged()
    {
        $exception = new RuntimeException('Provider down');
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform(static fn () => throw $exception), 'gpt-4.1', name: 'support'), $this->tracer);

        try {
            $agent->call(new MessageBag(Message::ofUser('Hi')))->getResult();
            $this->fail('The exception must reach the caller');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $span = $this->span('invoke_agent support');
        $this->assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        $this->assertSame(RuntimeException::class, $span->getAttributes()->get('error.type'));
    }

    public function testUserIdIsRecordedWhenResolved()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', name: 'support'), $this->tracer, userIdResolver: $this->userIdResolver('jane@example.com'));

        $agent->call(new MessageBag(Message::ofUser('Hi')))->getResult();

        $this->assertSame('jane@example.com', $this->span('invoke_agent support')->getAttributes()->get('user.id'));
    }

    public function testNameIsTakenFromTheDecoratedAgent()
    {
        $agent = new TracingAgent(new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4.1', name: 'support'), $this->tracer);

        $this->assertSame('support', $agent->getName());
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

#[AsTool('clock', 'Returns the current time')]
final class Clock
{
    public function __invoke(): string
    {
        return 'noon';
    }
}
