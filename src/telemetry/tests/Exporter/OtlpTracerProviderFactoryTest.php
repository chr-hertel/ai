<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Tests\Exporter;

use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface as ApiTracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Http\Psr\Client\Discovery;
use OpenTelemetry\SDK\Common\Http\Psr\Client\Discovery\DiscoveryInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Symfony\AI\Telemetry\Exporter\BackendInterface;
use Symfony\AI\Telemetry\Exporter\OtlpBackend;
use Symfony\AI\Telemetry\Exporter\OtlpTracerProviderFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OtlpTracerProviderFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Discovery::reset();
    }

    public function testEmptyEndpointDisablesTheExport()
    {
        $this->assertInstanceOf(NoopTracerProvider::class, OtlpTracerProviderFactory::create(new OtlpBackend('')));
    }

    public function testSpansAreExportedWithTheHeadersAndResourceAttributesOfTheBackend()
    {
        $requests = $this->export(static fn () => OtlpTracerProviderFactory::create(new OtlpBackend('https://otel.example.com/', ['Authorization' => 'Basic cGs6c2s='], 'http/json', ['service.name' => 'backend', 'deployment.environment' => 'prod']), ['service.name' => 'shop']), '{}');

        $this->assertCount(1, $requests);
        $this->assertSame('POST', $requests[0]['method']);
        $this->assertSame('https://otel.example.com/v1/traces', $requests[0]['url']);
        $this->assertContains('Content-Type: application/json', $requests[0]['headers']);
        $this->assertContains('Accept-Encoding: identity', $requests[0]['headers']);
        $this->assertContains('Authorization: Basic cGs6c2s=', $requests[0]['headers']);
        $this->assertStringContainsString('{"key":"service.name","value":{"stringValue":"shop"}}', $requests[0]['body'], 'The given attributes win over the ones of the backend');
        $this->assertStringContainsString('{"key":"deployment.environment","value":{"stringValue":"prod"}}', $requests[0]['body']);
    }

    public function testSpansAreExportedAsProtobufByDefault()
    {
        $requests = $this->export(static fn () => OtlpTracerProviderFactory::create(new OtlpBackend('http://localhost:4318')), '');

        $this->assertCount(1, $requests);
        $this->assertSame('http://localhost:4318/v1/traces', $requests[0]['url']);
        $this->assertContains('Content-Type: application/x-protobuf', $requests[0]['headers']);
    }

    public function testTheBackendDecoratesTheExporter()
    {
        $backend = new class implements BackendInterface {
            public ?SpanExporterInterface $decorated = null;

            public function getEndpoint(): string
            {
                return 'http://localhost:4318';
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
                return $this->decorated = $exporter;
            }
        };

        $this->export(static fn () => OtlpTracerProviderFactory::create($backend), '');

        $this->assertInstanceOf(SpanExporter::class, $backend->decorated);
    }

    /**
     * @param \Closure(): ApiTracerProviderInterface $createTracerProvider
     *
     * @return list<array{method: string, url: string, headers: list<string>, body: string}>
     */
    private function export(\Closure $createTracerProvider, string $responseBody): array
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, $responseBody) {
            $requests[] = ['method' => $method, 'url' => $url, 'headers' => $options['headers'], 'body' => $options['body']];

            return new MockResponse($responseBody);
        });
        Discovery::setDiscoverers([new class(new Psr18Client($httpClient)) implements DiscoveryInterface {
            public function __construct(
                private readonly ClientInterface $client,
            ) {
            }

            public function available(): bool
            {
                return true;
            }

            public function create(mixed $options): ClientInterface
            {
                return $this->client;
            }
        }]);

        $tracerProvider = $createTracerProvider();
        $this->assertInstanceOf(TracerProviderInterface::class, $tracerProvider);

        $tracerProvider->getTracer('test')->spanBuilder('chat gpt-4o')->startSpan()->end();
        $tracerProvider->forceFlush();

        return $requests;
    }
}
