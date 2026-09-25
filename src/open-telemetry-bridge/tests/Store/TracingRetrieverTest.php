<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\Tests\Store;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use PHPUnit\Framework\TestCase;
use Symfony\AI\OpenTelemetryBridge\Store\TracingRetriever;
use Symfony\AI\OpenTelemetryBridge\Tests\InMemoryTracingTrait;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\RetrieverInterface;

final class TracingRetrieverTest extends TestCase
{
    use InMemoryTracingTrait;

    public function testRetrievalProducesASpanAroundVectorizingAndQuerying()
    {
        $documents = [new VectorDocument('doc-1', new Vector([0.1, 0.2]))];
        $retriever = new TracingRetriever($this->retriever(function () use ($documents) {
            $this->tracer->spanBuilder('embeddings text-embedding-3-small')->startSpan()->end();

            // Lazy on purpose: the query must run inside the span
            yield from $documents;
        }), $this->tracer, 'blog');

        $this->assertSame($documents, iterator_to_array($retriever->retrieve('symfony')));

        $span = $this->span('retrieval blog');
        $this->assertSame(SpanKind::KIND_CLIENT, $span->getKind());
        $this->assertSame('retrieval', $span->getAttributes()->get('gen_ai.operation.name'));
        $this->assertSame('blog', $span->getAttributes()->get('gen_ai.data_source.id'));
        $this->assertSame(['embeddings text-embedding-3-small'], $this->childNames($span));
    }

    public function testFailingRetrievalMarksTheSpanAndIsRethrownUnchanged()
    {
        $exception = new RuntimeException('Store down');
        $retriever = new TracingRetriever($this->retriever(static fn () => throw $exception), $this->tracer, 'blog');

        try {
            $retriever->retrieve('symfony');
            $this->fail('The exception must reach the caller');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertSame(StatusCode::STATUS_ERROR, $this->span('retrieval blog')->getStatus()->getCode());
    }

    /**
     * @param \Closure(): iterable<VectorDocument> $retrieve
     */
    private function retriever(\Closure $retrieve): RetrieverInterface
    {
        return new class($retrieve) implements RetrieverInterface {
            public function __construct(
                private readonly \Closure $retrieve,
            ) {
            }

            public function retrieve(string $query, array $options = []): iterable
            {
                return ($this->retrieve)();
            }
        };
    }
}
