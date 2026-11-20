<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Bridge\Jaeger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Telemetry\Bridge\Jaeger\JaegerBackend;

final class JaegerBackendTest extends TestCase
{
    public function testExportsProtobufToTheCollector()
    {
        $backend = new JaegerBackend();

        $this->assertSame('http://localhost:4318', $backend->getEndpoint());
        $this->assertSame('http/protobuf', $backend->getProtocol());
        $this->assertSame([], $backend->getHeaders());
        $this->assertSame([], $backend->getResourceAttributes());
    }
}
