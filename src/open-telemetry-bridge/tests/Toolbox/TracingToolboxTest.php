<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\Tests\Toolbox;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\OpenTelemetryBridge\Tests\InMemoryTracingTrait;
use Symfony\AI\OpenTelemetryBridge\Toolbox\TracingToolbox;
use Symfony\AI\Platform\Result\ToolCall;

final class TracingToolboxTest extends TestCase
{
    use InMemoryTracingTrait;

    public function testToolCallProducesAnExecuteToolSpan()
    {
        $toolCall = new ToolCall('call_1', 'weather', ['city' => 'Berlin']);
        $toolbox = new TracingToolbox($this->toolbox(function () use ($toolCall) {
            $this->tracer->spanBuilder('HTTP GET')->startSpan()->end();

            return new ToolResult($toolCall, ['temperature' => 21]);
        }), $this->tracer, true);

        $toolbox->execute($toolCall);

        $span = $this->span('execute_tool weather');
        $attributes = $span->getAttributes();
        $this->assertSame(SpanKind::KIND_INTERNAL, $span->getKind());
        $this->assertSame('execute_tool', $attributes->get('gen_ai.operation.name'));
        $this->assertSame('weather', $attributes->get('gen_ai.tool.name'));
        $this->assertSame('call_1', $attributes->get('gen_ai.tool.call.id'));
        $this->assertSame('function', $attributes->get('gen_ai.tool.type'));
        $this->assertSame('{"city":"Berlin"}', $attributes->get('gen_ai.tool.call.arguments'));
        $this->assertSame('{"temperature":21}', $attributes->get('gen_ai.tool.call.result'));
        $this->assertSame(['HTTP GET'], $this->childNames($span));
    }

    public function testArgumentsAndResultAreNotCapturedByDefault()
    {
        $toolCall = new ToolCall('call_1', 'weather', ['city' => 'Berlin']);
        $toolbox = new TracingToolbox($this->toolbox(static fn () => new ToolResult($toolCall, 'sunny')), $this->tracer);

        $toolbox->execute($toolCall);

        $attributes = $this->span('execute_tool weather')->getAttributes();
        $this->assertNull($attributes->get('gen_ai.tool.call.arguments'));
        $this->assertNull($attributes->get('gen_ai.tool.call.result'));
    }

    public function testFailingToolMarksTheSpanAndIsRethrownUnchanged()
    {
        $exception = new RuntimeException('Weather service down');
        $toolbox = new TracingToolbox($this->toolbox(static fn () => throw $exception), $this->tracer);

        try {
            $toolbox->execute(new ToolCall('call_1', 'weather'));
            $this->fail('The exception must reach the caller');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertSame(StatusCode::STATUS_ERROR, $this->span('execute_tool weather')->getStatus()->getCode());
    }

    public function testThrowingTracerDoesNotBreakTheCall()
    {
        $tracer = $this->createStub(TracerInterface::class);
        $tracer->method('spanBuilder')->willThrowException(new RuntimeException('Tracer broken'));
        $toolCall = new ToolCall('call_1', 'weather');

        $toolbox = new TracingToolbox($this->toolbox(static fn () => new ToolResult($toolCall, 'sunny')), $tracer);

        $this->assertSame('sunny', $toolbox->execute($toolCall)->getResult());
    }

    /**
     * @param \Closure(): ToolResult $execute
     */
    private function toolbox(\Closure $execute): ToolboxInterface
    {
        return new class($execute) implements ToolboxInterface {
            public function __construct(
                private readonly \Closure $execute,
            ) {
            }

            public function getTools(): array
            {
                return [];
            }

            public function execute(ToolCall $toolCall): ToolResult
            {
                return ($this->execute)();
            }
        };
    }
}
