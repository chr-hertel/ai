<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tracing;

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
 * Builds the tracer provider of the "ai.tracing.exporter" shortcut: OTLP over HTTP, nothing else to configure.
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
     * @param array<string, string>                    $headers
     * @param 'http/protobuf'|'http/json'              $protocol
     * @param array<non-empty-string, bool|int|string> $resourceAttributes
     */
    public static function create(string $endpoint, array $headers = [], string $protocol = 'http/protobuf', array $resourceAttributes = []): TracerProviderInterface
    {
        if ('' === $endpoint) {
            return new NoopTracerProvider();
        }

        $transport = (new OtlpHttpTransportFactory())->create(
            rtrim($endpoint, '/').'/v1/traces',
            self::CONTENT_TYPES[$protocol],
            // The PSR-18 client of symfony/http-client decodes compressed responses already, the transport would decode them again
            ['Accept-Encoding' => 'identity', ...$headers],
        );

        return TracerProvider::builder()
            ->addSpanProcessor(new BatchSpanProcessor(new SpanExporter($transport), Clock::getDefault()))
            ->setResource(ResourceInfoFactory::defaultResource()->merge(ResourceInfo::create(Attributes::create($resourceAttributes))))
            ->build();
    }
}
