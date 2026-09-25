<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Telemetry;

use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * Application-side OpenTelemetry setup: any OTLP/HTTP backend, configured by the standard OTEL_* variables.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TracerProviderFactory
{
    public static function create(string $endpoint, string $headers, string $serviceName): TracerProviderInterface
    {
        if ('' === $endpoint) {
            return new NoopTracerProvider();
        }

        $transport = (new OtlpHttpTransportFactory())->create(
            rtrim($endpoint, '/').'/v1/traces',
            'application/json',
            // Symfony's PSR-18 client already decodes gzip responses, the transport would decode them a second time
            ['Accept-Encoding' => 'identity', ...self::parseHeaders($headers)],
        );

        $resource = ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
            'service.name' => $serviceName,
        ])));

        return TracerProvider::builder()
            ->addSpanProcessor(new BatchSpanProcessor(new SpanExporter($transport), Clock::getDefault()))
            ->setResource($resource)
            ->build();
    }

    /**
     * Parses the "key=value,key=value" format of OTEL_EXPORTER_OTLP_HEADERS, values being URL-encoded.
     *
     * @return array<string, string>
     */
    private static function parseHeaders(string $headers): array
    {
        $parsed = [];
        foreach (explode(',', $headers) as $header) {
            if (!str_contains($header, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $header, 2);
            $parsed[trim($name)] = trim(rawurldecode($value));
        }

        return $parsed;
    }
}
