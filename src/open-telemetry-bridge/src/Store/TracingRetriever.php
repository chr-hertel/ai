<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\Store;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use Symfony\AI\OpenTelemetryBridge\Guard;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\GenAiAttributes;
use Symfony\AI\Store\RetrieverInterface;

/**
 * Produces a retrieval span around vectorizing the query and searching the store.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TracingRetriever implements RetrieverInterface
{
    public function __construct(
        private readonly RetrieverInterface $retriever,
        private readonly TracerInterface $tracer,
        private readonly string $dataSource,
    ) {
    }

    public function retrieve(string $query, array $options = []): iterable
    {
        $span = Guard::run(fn () => $this->tracer->spanBuilder(GenAiAttributes::spanName(GenAiAttributes::OPERATION_RETRIEVAL, $this->dataSource))
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(GenAiAttributes::OPERATION_NAME, GenAiAttributes::OPERATION_RETRIEVAL)
            ->setAttribute(GenAiAttributes::DATA_SOURCE_ID, $this->dataSource)
            ->startSpan());

        if (null === $span) {
            return $this->retriever->retrieve($query, $options);
        }

        try {
            // Materialized inside the span, a lazy result would otherwise query after the span ended
            return Guard::inContext($span->storeInContext(Context::getCurrent()), fn (): array => iterator_to_array($this->retriever->retrieve($query, $options), false));
        } catch (\Throwable $exception) {
            Guard::recordError($span, $exception);

            throw $exception;
        } finally {
            Guard::run(static fn () => $span->end());
        }
    }
}
