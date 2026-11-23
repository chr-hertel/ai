<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Bridge\Tempo;

use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Symfony\AI\Telemetry\Exporter\BackendInterface;

/**
 * Grafana Tempo, with the tenant of a multi-tenant setup.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TempoBackend implements BackendInterface
{
    public function __construct(
        private readonly string $endpoint = 'http://localhost:4318',
        private readonly ?string $tenant = null,
    ) {
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getHeaders(): array
    {
        return null === $this->tenant ? [] : ['X-Scope-OrgID' => $this->tenant];
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
