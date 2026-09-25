<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Tracing;

use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\SDK\Common\Http\Psr\Client\Discovery;
use OpenTelemetry\SDK\Common\Http\Psr\Client\Discovery\DiscoveryInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Symfony\AI\AiBundle\Tracing\OtlpTracerProviderFactory;
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
        $this->assertInstanceOf(NoopTracerProvider::class, OtlpTracerProviderFactory::create(''));
    }

    public function testSpansAreExportedAsJsonWithTheConfiguredHeaders()
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = ['method' => $method, 'url' => $url, 'headers' => $options['headers']];

            return new MockResponse('{}', ['response_headers' => ['content-type' => 'application/json']]);
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

        $tracerProvider = OtlpTracerProviderFactory::create('https://langfuse.example.com/api/public/otel/', ['Authorization' => 'Basic cGs6c2s=']);
        $this->assertInstanceOf(TracerProviderInterface::class, $tracerProvider);

        $tracerProvider->getTracer('test')->spanBuilder('chat gpt-4o')->startSpan()->end();
        $tracerProvider->forceFlush();

        $this->assertCount(1, $requests);
        $this->assertSame('POST', $requests[0]['method']);
        $this->assertSame('https://langfuse.example.com/api/public/otel/v1/traces', $requests[0]['url']);
        $this->assertContains('Content-Type: application/json', $requests[0]['headers']);
        $this->assertContains('Accept-Encoding: identity', $requests[0]['headers']);
        $this->assertContains('Authorization: Basic cGs6c2s=', $requests[0]['headers']);
    }
}
