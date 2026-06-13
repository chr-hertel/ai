<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Command;

use Mcp\Client;
use Mcp\Client\Configuration;
use Mcp\Client\Protocol;
use Mcp\Exception\ConnectionException;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Implementation;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool as McpTool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Command\McpClientDebugCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class McpClientDebugCommandTest extends TestCase
{
    public function testListsToolsForKnownClient()
    {
        $client = $this->createClientMock();
        $client->method('listTools')->willReturn(new ListToolsResult([
            new McpTool(
                name: 'read_file',
                title: null,
                inputSchema: ['type' => 'object', 'properties' => ['path' => ['type' => 'string']], 'required' => ['path']],
                description: 'Read a file from disk.',
                annotations: null,
            ),
        ]));
        $client->method('getServerInfo')->willReturn(new Implementation('filesystem-server', '1.0.0'));
        $client->method('getInstructions')->willReturn('Mount only /tmp.');

        $tester = $this->createTester(['filesystem' => $client]);
        $tester->execute(['name' => 'filesystem']);
        $tester->assertCommandIsSuccessful();

        $output = $tester->getDisplay();
        $this->assertStringContainsString('filesystem-server', $output);
        $this->assertStringContainsString('Mount only /tmp.', $output);
        $this->assertStringContainsString('read_file', $output);
        $this->assertStringContainsString('Read a file from disk.', $output);
        $this->assertStringContainsString('path', $output);
    }

    public function testReportsUnknownClient()
    {
        $tester = $this->createTester(['filesystem' => $this->createClientMock()]);
        $exit = $tester->execute(['name' => 'missing']);

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('No MCP client named "missing"', $tester->getDisplay());
        $this->assertStringContainsString('Available: filesystem', $tester->getDisplay());
    }

    public function testReportsConnectionFailure()
    {
        $client = $this->createClientMock();
        $client->method('listTools')->willThrowException(new ConnectionException('handshake failed'));

        $tester = $this->createTester(['filesystem' => $client]);
        $exit = $tester->execute(['name' => 'filesystem']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Failed to connect to or query the server: handshake failed', $tester->getDisplay());
    }

    /**
     * @param array<string, Client> $clients
     */
    private function createTester(array $clients): CommandTester
    {
        $locator = new ServiceLocator(array_map(
            static fn (Client $c): \Closure => static fn (): Client => $c,
            $clients,
        ));

        return new CommandTester(new McpClientDebugCommand($locator));
    }

    /**
     * @return Client&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createClientMock(): Client
    {
        return $this->getMockBuilder(Client::class)
            ->setConstructorArgs([
                new Protocol(),
                new Configuration(new Implementation('test', '1.0'), new ClientCapabilities()),
            ])
            ->onlyMethods(['connect', 'disconnect', 'listTools', 'callTool', 'getServerInfo', 'getInstructions', 'isConnected'])
            ->getMock();
    }
}
