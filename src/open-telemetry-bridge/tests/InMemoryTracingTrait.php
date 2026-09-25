<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\Tests;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

trait InMemoryTracingTrait
{
    private InMemoryExporter $exporter;
    private TracerInterface $tracer;

    protected function setUp(): void
    {
        $this->exporter = new InMemoryExporter();
        $this->tracer = (new TracerProvider(new SimpleSpanProcessor($this->exporter)))->getTracer('test');
    }

    /**
     * @return list<ImmutableSpan>
     */
    private function spans(): array
    {
        $spans = [];
        foreach ($this->exporter->getSpans() as $span) {
            \assert($span instanceof ImmutableSpan);
            $spans[] = $span;
        }

        // Spans are exported when they end, sort them back into start order
        usort($spans, static fn (ImmutableSpan $a, ImmutableSpan $b) => $a->getStartEpochNanos() <=> $b->getStartEpochNanos());

        return $spans;
    }

    /**
     * @return list<ImmutableSpan>
     */
    private function spansNamed(string $name): array
    {
        return array_values(array_filter($this->spans(), static fn (ImmutableSpan $span) => $span->getName() === $name));
    }

    private function span(string $name): ImmutableSpan
    {
        $spans = $this->spansNamed($name);
        $this->assertCount(1, $spans, \sprintf('Expected exactly one span named "%s".', $name));

        return $spans[0];
    }

    /**
     * @return list<string>
     */
    private function childNames(ImmutableSpan $parent): array
    {
        $names = [];
        foreach ($this->spans() as $span) {
            if ($span->getParentSpanId() === $parent->getSpanId()) {
                $names[] = $span->getName();
            }
        }

        return $names;
    }
}
