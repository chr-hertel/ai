<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Bridge\Tempo\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Telemetry\Bridge\Tempo\TempoBackend;

final class TempoBackendTest extends TestCase
{
    public function testExportsProtobufWithoutTenantByDefault()
    {
        $backend = new TempoBackend('http://tempo:4318');

        $this->assertSame('http://tempo:4318', $backend->getEndpoint());
        $this->assertSame('http/protobuf', $backend->getProtocol());
        $this->assertSame([], $backend->getHeaders());
    }

    public function testSendsTheTenant()
    {
        $this->assertSame(['X-Scope-OrgID' => 'shop'], (new TempoBackend(tenant: 'shop'))->getHeaders());
    }
}
