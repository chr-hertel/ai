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
use Mcp\Exception\RequestException;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Implementation;
use Mcp\Schema\Result\CallToolResult;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ToolCallException;
use Symfony\AI\Agent\Bridge\Mcp\McpClient;
use Symfony\AI\Agent\Bridge\Mcp\McpClientAdapter;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class McpClientAdapterTest extends TestCase
{
    public function testExecuteForwardsTheRemoteToolNameAndArguments()
    {
        $client = $this->createClient();
        $client->expects($this->once())
            ->method('callTool')
            ->with('echo', ['msg' => 'hello'])
            ->willReturn(new CallToolResult([new TextContent('echoed: hello')]));

        $adapter = new McpClientAdapter(new McpClient('demo', $client, $this->createMock(TransportInterface::class)));

        $metadata = new Tool(new ExecutionReference(McpClientAdapter::class, 'echo'), 'demo_echo', '');
        $result = $adapter->execute($metadata, new ToolCall('call_1', 'demo_echo', ['msg' => 'hello']));

        $this->assertSame('echoed: hello', $result);
    }

    public function testExecuteReturnsStructuredContentWhenProvided()
    {
        $client = $this->createClient();
        $client->method('callTool')->willReturn(
            new CallToolResult([new TextContent('ignored')], false, ['answer' => 42]),
        );

        $adapter = new McpClientAdapter(new McpClient('demo', $client, $this->createMock(TransportInterface::class)));
        $metadata = new Tool(new ExecutionReference(McpClientAdapter::class, 'calc'), 'demo_calc', '');

        $this->assertSame(['answer' => 42], $adapter->execute($metadata, new ToolCall('call_2', 'demo_calc')));
    }

    public function testExecuteWrapsServerSideErrorAsToolCallException()
    {
        $client = $this->createClient();
        $client->method('callTool')->willReturn(
            new CallToolResult([new TextContent('boom')], true),
        );

        $adapter = new McpClientAdapter(new McpClient('demo', $client, $this->createMock(TransportInterface::class)));
        $metadata = new Tool(new ExecutionReference(McpClientAdapter::class, 'oops'), 'demo_oops', '');

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Tool "oops" on MCP server "demo" returned an error: boom');

        $adapter->execute($metadata, new ToolCall('call_3', 'demo_oops'));
    }

    public function testExecuteWrapsSdkExceptions()
    {
        $client = $this->createClient();
        $client->method('callTool')->willThrowException(new RequestException('rpc fail'));

        $adapter = new McpClientAdapter(new McpClient('demo', $client, $this->createMock(TransportInterface::class)));
        $metadata = new Tool(new ExecutionReference(McpClientAdapter::class, 'die'), 'demo_die', '');

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Tool "die" on MCP server "demo" failed: rpc fail');

        $adapter->execute($metadata, new ToolCall('call_4', 'demo_die'));
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
