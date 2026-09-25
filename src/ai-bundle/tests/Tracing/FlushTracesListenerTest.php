<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Tracing;

use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\AiBundle\Tracing\FlushTracesListener;

final class FlushTracesListenerTest extends TestCase
{
    public function testBatchedSpansAreExported()
    {
        $exporter = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new BatchSpanProcessor($exporter, Clock::getDefault()));
        $tracerProvider->getTracer('test')->spanBuilder('chat gpt-4o')->startSpan()->end();
        $this->assertCount(0, $exporter->getSpans(), 'The span waits in the batch');

        (new FlushTracesListener($tracerProvider))();

        $this->assertCount(1, $exporter->getSpans());
    }

    public function testNoopTracerProviderIsIgnored()
    {
        (new FlushTracesListener(new NoopTracerProvider()))();

        $this->addToAssertionCount(1);
    }
}
