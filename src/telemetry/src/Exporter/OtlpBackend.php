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
use Symfony\AI\Telemetry\Exception\InvalidArgumentException;

/**
 * Any backend ingesting OTLP over HTTP, configured by hand.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class OtlpBackend implements BackendInterface
{
    /**
     * @param array<string, string>                    $headers
     * @param 'http/protobuf'|'http/json'              $protocol
     * @param array<non-empty-string, bool|int|string> $resourceAttributes
     */
    public function __construct(
        private readonly string $endpoint,
        private readonly array $headers = [],
        private readonly string $protocol = 'http/protobuf',
        private readonly array $resourceAttributes = [],
    ) {
        if (!\in_array($protocol, ['http/protobuf', 'http/json'], true)) {
            throw new InvalidArgumentException(\sprintf('The OTLP protocol must be "http/protobuf" or "http/json", "%s" given.', $protocol));
        }
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getResourceAttributes(): array
    {
        return $this->resourceAttributes;
    }

    public function decorate(SpanExporterInterface $exporter): SpanExporterInterface
    {
        return $exporter;
    }
}
