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

use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Symfony\AI\Telemetry\Exporter\BackendInterface;

/**
 * Arize Phoenix, self-hosted or in the cloud, with the traces grouped by project.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PhoenixBackend implements BackendInterface
{
    public function __construct(
        private readonly string $endpoint = 'http://localhost:6006',
        private readonly ?string $project = null,
        private readonly ?string $apiKey = null,
    ) {
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getHeaders(): array
    {
        return null === $this->apiKey ? [] : ['Authorization' => 'Bearer '.$this->apiKey];
    }

    public function getProtocol(): string
    {
        // Phoenix doesn't accept JSON over HTTP
        return 'http/protobuf';
    }

    public function getResourceAttributes(): array
    {
        return null === $this->project ? [] : ['openinference.project.name' => $this->project];
    }

    public function decorate(SpanExporterInterface $exporter): SpanExporterInterface
    {
        return new OpenInferenceSpanExporter($exporter);
    }
}
