<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Bridge\Phoenix\Tests;

use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Telemetry\Bridge\Phoenix\OpenInferenceSpanExporter;
use Symfony\AI\Telemetry\Bridge\Phoenix\PhoenixBackend;

final class PhoenixBackendTest extends TestCase
{
    public function testExportsProtobufToTheLocalPhoenixByDefault()
    {
        $backend = new PhoenixBackend();

        $this->assertSame('http://localhost:6006', $backend->getEndpoint());
        $this->assertSame('http/protobuf', $backend->getProtocol());
        $this->assertSame([], $backend->getHeaders());
        $this->assertSame([], $backend->getResourceAttributes());
    }

    public function testGroupsTheTracesByProjectAndAuthenticatesWithTheApiKey()
    {
        $backend = new PhoenixBackend('https://app.phoenix.arize.com/s/acme', 'Shop', 'secret');

        $this->assertSame(['openinference.project.name' => 'Shop'], $backend->getResourceAttributes());
        $this->assertSame(['Authorization' => 'Bearer secret'], $backend->getHeaders());
    }

    public function testAddsTheOpenInferenceAttributes()
    {
        $this->assertInstanceOf(OpenInferenceSpanExporter::class, (new PhoenixBackend())->decorate(new InMemoryExporter()));
    }
}
