<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Bridge\Jaeger;

use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Symfony\AI\Telemetry\Exporter\BackendInterface;

/**
 * Jaeger, receiving OTLP on its collector port.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class JaegerBackend implements BackendInterface
{
    public function __construct(
        private readonly string $endpoint = 'http://localhost:4318',
    ) {
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getHeaders(): array
    {
        return [];
    }

    public function getProtocol(): string
    {
        return 'http/protobuf';
    }

    public function getResourceAttributes(): array
    {
        return [];
    }

    public function decorate(SpanExporterInterface $exporter): SpanExporterInterface
    {
        return $exporter;
    }
}
