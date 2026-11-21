<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Bridge\Langfuse\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Telemetry\Bridge\Langfuse\LangfuseBackend;

final class LangfuseBackendTest extends TestCase
{
    public function testExportsToTheOtelEndpointOfTheHost()
    {
        $backend = new LangfuseBackend('pk-lf-1', 'sk-lf-1', 'http://localhost:3000/');

        $this->assertSame('http://localhost:3000/api/public/otel', $backend->getEndpoint());
        $this->assertSame('http/json', $backend->getProtocol());
        $this->assertSame([], $backend->getResourceAttributes());
    }

    public function testDefaultsToTheEuCloud()
    {
        $this->assertSame('https://cloud.langfuse.com/api/public/otel', (new LangfuseBackend('pk-lf-1', 'sk-lf-1'))->getEndpoint());
    }

    public function testEmptyHostDisablesTheExport()
    {
        $this->assertSame('', (new LangfuseBackend('pk-lf-1', 'sk-lf-1', ''))->getEndpoint());
    }

    public function testAuthenticatesWithTheKeysOfTheProject()
    {
        $headers = (new LangfuseBackend('pk-lf-1', 'sk-lf-1'))->getHeaders();

        $this->assertSame('Basic '.base64_encode('pk-lf-1:sk-lf-1'), $headers['Authorization']);
        $this->assertSame('4', $headers['x-langfuse-ingestion-version']);
    }
}
