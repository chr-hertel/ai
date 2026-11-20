<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Bridge\Phoenix\Tests;

use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Telemetry\Bridge\Phoenix\OpenInferenceSpanExporter;

final class OpenInferenceSpanExporterTest extends TestCase
{
    public function testAgentSpansGetTheLatestUserMessageAndTheAnswer()
    {
        $attributes = $this->export([
            'gen_ai.operation.name' => 'invoke_agent',
            'gen_ai.input.messages' => '[{"role":"user","parts":[{"type":"text","content":"Hi"}]},{"role":"assistant","parts":[{"type":"text","content":"Hello"}]},{"role":"user","parts":[{"type":"text","content":"Where is "},{"type":"text","content":"my order?"}]}]',
            'gen_ai.output.messages' => '[{"role":"assistant","parts":[{"type":"text","content":"It is shipped."}],"finish_reason":"stop"}]',
        ]);

        $this->assertSame('Where is my order?', $attributes->get('input.value'));
        $this->assertSame('It is shipped.', $attributes->get('output.value'));
        $this->assertSame('invoke_agent', $attributes->get('gen_ai.operation.name'));
    }

    public function testOtherSpansStayUnchanged()
    {
        $attributes = $this->export([
            'gen_ai.operation.name' => 'chat',
            'gen_ai.input.messages' => '[{"role":"user","parts":[{"type":"text","content":"Hi"}]}]',
        ]);

        $this->assertNull($attributes->get('input.value'));
    }

    public function testAgentSpansWithoutContentStayUnchanged()
    {
        $attributes = $this->export(['gen_ai.operation.name' => 'invoke_agent']);

        $this->assertNull($attributes->get('input.value'));
        $this->assertNull($attributes->get('output.value'));
    }

    /**
     * @param array<non-empty-string, string> $attributes
     *
     * @return AttributesInterface&iterable<string, mixed>
     */
    private function export(array $attributes): AttributesInterface
    {
        $memory = new InMemoryExporter();
        $tracerProvider = TracerProvider::builder()->addSpanProcessor(new SimpleSpanProcessor(new OpenInferenceSpanExporter($memory)))->build();
        $tracerProvider->getTracer('test')->spanBuilder('span')->setAttributes($attributes)->startSpan()->end();

        $spans = iterator_to_array($memory->getSpans());
        $this->assertCount(1, $spans);
        $this->assertInstanceOf(SpanDataInterface::class, $spans[0]);

        return $spans[0]->getAttributes();
    }
}
