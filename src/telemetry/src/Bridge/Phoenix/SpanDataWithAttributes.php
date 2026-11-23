<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Bridge\Phoenix;

use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\StatusDataInterface;

/**
 * @internal
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SpanDataWithAttributes implements SpanDataInterface
{
    /**
     * @param AttributesInterface&iterable<string, mixed> $attributes
     */
    public function __construct(
        private readonly SpanDataInterface $span,
        private readonly AttributesInterface $attributes,
    ) {
    }

    public function getName(): string
    {
        return $this->span->getName();
    }

    public function getKind(): int
    {
        return $this->span->getKind();
    }

    public function getContext(): SpanContextInterface
    {
        return $this->span->getContext();
    }

    public function getParentContext(): SpanContextInterface
    {
        return $this->span->getParentContext();
    }

    public function getTraceId(): string
    {
        return $this->span->getTraceId();
    }

    public function getSpanId(): string
    {
        return $this->span->getSpanId();
    }

    public function getParentSpanId(): string
    {
        return $this->span->getParentSpanId();
    }

    public function getStatus(): StatusDataInterface
    {
        return $this->span->getStatus();
    }

    public function getStartEpochNanos(): int
    {
        return $this->span->getStartEpochNanos();
    }

    /**
     * @return AttributesInterface&iterable<string, mixed>
     */
    public function getAttributes(): AttributesInterface
    {
        return $this->attributes;
    }

    public function getEvents(): array
    {
        return $this->span->getEvents();
    }

    public function getLinks(): array
    {
        return $this->span->getLinks();
    }

    public function getEndEpochNanos(): int
    {
        return $this->span->getEndEpochNanos();
    }

    public function hasEnded(): bool
    {
        return $this->span->hasEnded();
    }

    public function getInstrumentationScope(): InstrumentationScopeInterface
    {
        return $this->span->getInstrumentationScope();
    }

    public function getResource(): ResourceInfo
    {
        return $this->span->getResource();
    }

    public function getTotalDroppedEvents(): int
    {
        return $this->span->getTotalDroppedEvents();
    }

    public function getTotalDroppedLinks(): int
    {
        return $this->span->getTotalDroppedLinks();
    }
}
