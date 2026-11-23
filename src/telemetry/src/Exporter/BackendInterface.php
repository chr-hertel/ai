<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Exporter;

use OpenTelemetry\SDK\Trace\SpanExporterInterface;

/**
 * A tracing backend receiving the spans over OTLP/HTTP.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface BackendInterface
{
    /**
     * Base URL of the OTLP endpoint, "/v1/traces" is appended; the export is disabled when empty.
     */
    public function getEndpoint(): string;

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * @return 'http/protobuf'|'http/json'
     */
    public function getProtocol(): string;

    /**
     * @return array<non-empty-string, bool|int|string>
     */
    public function getResourceAttributes(): array;

    /**
     * Adapts the spans to the backend before they are sent.
     */
    public function decorate(SpanExporterInterface $exporter): SpanExporterInterface;
}
