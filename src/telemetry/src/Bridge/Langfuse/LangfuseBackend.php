<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Bridge\Langfuse;

use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Symfony\AI\Telemetry\Exporter\BackendInterface;

/**
 * Langfuse, in the cloud or self-hosted, authenticated with the public and secret key of a project.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LangfuseBackend implements BackendInterface
{
    public function __construct(
        private readonly string $publicKey,
        private readonly string $secretKey,
        private readonly string $host = 'https://cloud.langfuse.com',
    ) {
    }

    public function getEndpoint(): string
    {
        return '' === $this->host ? '' : rtrim($this->host, '/').'/api/public/otel';
    }

    public function getHeaders(): array
    {
        return [
            'Authorization' => 'Basic '.base64_encode($this->publicKey.':'.$this->secretKey),
            // without it, Langfuse shows the spans with a delay of up to 10 minutes
            'x-langfuse-ingestion-version' => '4',
        ];
    }

    public function getProtocol(): string
    {
        // the PHP exporter fails to decode the protobuf response body of Langfuse
        return 'http/json';
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
