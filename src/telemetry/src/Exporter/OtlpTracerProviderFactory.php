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
 * Builds a tracer provider exporting to a backend with OTLP over HTTP, requires "open-telemetry/sdk" and
 * "open-telemetry/exporter-otlp".
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class OtlpTracerProviderFactory
{
    private const CONTENT_TYPES = [
        'http/protobuf' => 'application/x-protobuf',
        'http/json' => 'application/json',
    ];

    /**
     * @param array<non-empty-string, bool|int|string> $resourceAttributes merged over the ones of the backend
     */
    public static function create(BackendInterface $backend, array $resourceAttributes = []): TracerProviderInterface
    {
        if ('' === $backend->getEndpoint()) {
            return new NoopTracerProvider();
        }

        $transport = (new OtlpHttpTransportFactory())->create(
            rtrim($backend->getEndpoint(), '/').'/v1/traces',
            self::CONTENT_TYPES[$backend->getProtocol()],
            // The PSR-18 client of symfony/http-client decodes compressed responses already, the transport would decode them again
            ['Accept-Encoding' => 'identity', ...$backend->getHeaders()],
        );
        $attributes = [...$backend->getResourceAttributes(), ...$resourceAttributes];

        return TracerProvider::builder()
            ->addSpanProcessor(new BatchSpanProcessor($backend->decorate(new SpanExporter($transport)), Clock::getDefault()))
            ->setResource(ResourceInfoFactory::defaultResource()->merge(ResourceInfo::create(Attributes::create($attributes))))
            ->build();
    }
}
