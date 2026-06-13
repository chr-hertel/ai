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
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Implementation;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool as McpTool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Mcp\McpClient;
use Symfony\AI\Agent\Bridge\Mcp\McpClientAdapter;
use Symfony\AI\Agent\Bridge\Mcp\McpToolFactory;
use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Platform\Tool\Tool;

final class McpToolFactoryTest extends TestCase
{
    public function testYieldsToolsWithPrefixedNames()
    {
        $list = new ListToolsResult([
            new McpTool(
                name: 'echo',
                title: null,
                inputSchema: ['type' => 'object', 'properties' => ['msg' => ['type' => 'string']], 'required' => ['msg']],
                description: 'Echoes a message back.',
                annotations: null,
            ),
            new McpTool(
                name: 'sum',
                title: null,
                inputSchema: ['type' => 'object', 'properties' => ['a' => ['type' => 'integer'], 'b' => ['type' => 'integer']], 'required' => ['a', 'b']],
                description: null,
                annotations: null,
            ),
        ]);

        $client = $this->createConnectedClientReturning($list);

        $mcpClient = new McpClient('demo', $client, $this->createMock(TransportInterface::class));
        $adapter = new McpClientAdapter($mcpClient);
        $factory = new McpToolFactory();

        $tools = iterator_to_array($factory->getTool($adapter), false);

        $this->assertCount(2, $tools);
        $this->assertInstanceOf(Tool::class, $tools[0]);
        $this->assertSame('demo_echo', $tools[0]->getName());
        $this->assertSame('echo', $tools[0]->getReference()->getMethod());
        $this->assertSame(McpClientAdapter::class, $tools[0]->getReference()->getClass());
        $this->assertSame('Echoes a message back.', $tools[0]->getDescription());
        $this->assertSame(['type' => 'object', 'properties' => ['msg' => ['type' => 'string']], 'required' => ['msg']], $tools[0]->getParameters());

        $this->assertSame('demo_sum', $tools[1]->getName());
        $this->assertSame('sum', $tools[1]->getReference()->getMethod());
        $this->assertSame('', $tools[1]->getDescription());
    }

    public function testThrowsOnUnsupportedReference()
    {
        $factory = new McpToolFactory();

        $this->expectException(ToolException::class);
        iterator_to_array($factory->getTool(new \stdClass()), false);
    }

    /**
     * Build a Client double whose Protocol layer reports "initialized" so
     * isConnected() returns true, then have listTools() return a canned page.
     *
     * @return Client&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createConnectedClientReturning(ListToolsResult $list): Client
    {
        $client = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([
                new Protocol(),
                new Configuration(new Implementation('test', '1.0'), new ClientCapabilities()),
            ])
            ->onlyMethods(['connect', 'listTools', 'callTool', 'disconnect'])
            ->getMock();

        $client->method('connect');
        $client->method('listTools')->willReturn($list);

        return $client;
    }
}
