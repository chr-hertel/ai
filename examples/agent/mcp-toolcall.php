<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Mcp\Client;
use Mcp\Client\Transport\StdioTransport;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Bridge\Mcp\McpClient as McpBridgeClient;
use Symfony\AI\Agent\Bridge\Mcp\McpClientAdapter;
use Symfony\AI\Agent\Bridge\Mcp\McpToolFactory;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAiFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

/*
 * This example connects a Symfony AI Agent to a remote MCP server via stdio.
 *
 * Configure with:
 *   MCP_SERVER_NAME=filesystem
 *   MCP_SERVER_CMD="npx -y @modelcontextprotocol/server-filesystem /tmp"
 *   OPENAI_API_KEY=sk-...
 *
 * Any stdio-based MCP server works — point MCP_SERVER_CMD at the binary you
 * want to expose. The agent will discover the server's tools/list and call
 * them on demand.
 */

$serverName = env('MCP_SERVER_NAME');
$serverCmd = env('MCP_SERVER_CMD');

$parts = preg_split('/\s+/', trim($serverCmd)) ?: [];
$program = array_shift($parts);

if (null === $program) {
    output()->writeln('<error>MCP_SERVER_CMD is empty.</error>');
    exit(1);
}

$transport = new StdioTransport($program, $parts);
$client = Client::builder()
    ->setClientInfo('symfony-ai-example', '0.1')
    ->build();

$mcpClient = new McpBridgeClient($serverName, $client, $transport);
$adapter = new McpClientAdapter($mcpClient);

$toolbox = new Toolbox(
    tools: [$adapter],
    toolFactory: new ChainFactory([new McpToolFactory(), new ReflectionToolFactory()]),
);
$processor = new AgentProcessor(toolbox: $toolbox);

$platform = OpenAiFactory::createPlatform(env('OPENAI_API_KEY'), httpClient: http_client());
$agent = new Agent($platform, 'gpt-4o-mini', [$processor], [$processor]);

$messages = new MessageBag(
    Message::forSystem('Use the available MCP tools to answer the user.'),
    Message::ofUser('List the tools you have access to and briefly describe each one.'),
);

$result = $agent->call($messages);

output()->writeln($result->getContent());

$mcpClient->disconnect();
