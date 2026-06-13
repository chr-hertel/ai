<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Mcp\Tests;

use Mcp\Client;
use Mcp\Client\Configuration;
use Mcp\Client\Protocol;
use Mcp\Client\Transport\TransportInterface;
use Mcp\Exception\ConnectionException as McpConnectionException;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Implementation;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool as McpTool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ConnectionException;
use Symfony\AI\Agent\Bridge\Mcp\McpClient;

final class McpClientTest extends TestCase
{
    public function testConnectIsCalledOnceAcrossMultipleOperations()
    {
        $client = $this->createClient();
        $client->expects($this->once())->method('connect');
        $client->method('listTools')->willReturn(new ListToolsResult([]));

        $mcpClient = new McpClient('demo', $client, $this->createMock(TransportInterface::class));

        $mcpClient->listTools();
        $mcpClient->listTools();
    }

    public function testListToolsPaginatesAcrossCursors()
    {
        $page1 = new ListToolsResult(
            [$this->makeTool('a')],
            'cursor-1',
        );
        $page2 = new ListToolsResult(
            [$this->makeTool('b'), $this->makeTool('c')],
            null,
        );

        $client = $this->createClient();
        $client->method('connect');
        $client->expects($this->exactly(2))
            ->method('listTools')
            ->willReturnOnConsecutiveCalls($page1, $page2);

        $mcpClient = new McpClient('demo', $client, $this->createMock(TransportInterface::class));
        $tools = $mcpClient->listTools();

        $this->assertCount(3, $tools);
        $this->assertSame(['a', 'b', 'c'], array_map(static fn (McpTool $t) => $t->name, $tools));
    }

    public function testConnectFailureWrappedAsBridgeException()
    {
        $client = $this->createClient();
        $client->method('connect')->willThrowException(new McpConnectionException('handshake failed'));

        $mcpClient = new McpClient('demo', $client, $this->createMock(TransportInterface::class));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Failed to connect to MCP server "demo": handshake failed');

        $mcpClient->listTools();
    }

    public function testPrefixDefaultsToServerName()
    {
        $client = $this->createClient();
        $mcpClient = new McpClient('filesystem', $client, $this->createMock(TransportInterface::class));

        $this->assertSame('filesystem_', $mcpClient->getPrefix());
    }

    public function testExplicitPrefixWins()
    {
        $client = $this->createClient();
        $mcpClient = new McpClient('filesystem', $client, $this->createMock(TransportInterface::class), 'fs.');

        $this->assertSame('fs.', $mcpClient->getPrefix());
    }

    private function makeTool(string $name): McpTool
    {
        return new McpTool(
            name: $name,
            title: null,
            inputSchema: ['type' => 'object', 'properties' => [], 'required' => []],
            description: null,
            annotations: null,
        );
    }

    /**
     * @return Client&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createClient(): Client
    {
        return $this->getMockBuilder(Client::class)
            ->setConstructorArgs([
                new Protocol(),
                new Configuration(new Implementation('test', '1.0'), new ClientCapabilities()),
            ])
            ->onlyMethods(['connect', 'listTools', 'callTool', 'disconnect'])
            ->getMock();
    }
}
