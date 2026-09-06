<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\McpCallResult;
use Symfony\AI\Platform\Result\McpListToolsResult;
use Symfony\AI\Platform\Result\MultiPartResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::ofUser('What transport protocols does the 2025 version of the MCP specification support?'),
);

// Let the model call tools on a hosted (remote) MCP server. "deepwiki" is a public
// MCP server; "require_approval => never" skips the approval round-trip.
$result = $platform->invoke('gpt-4o-mini', $messages, [
    'tools' => [
        [
            'type' => 'mcp',
            'server_label' => 'deepwiki',
            'server_url' => 'https://mcp.deepwiki.com/mcp',
            'require_approval' => 'never',
        ],
    ],
]);

echo $result->asText().\PHP_EOL;

// The hosted MCP interactions are surfaced as McpListToolsResult / McpCallResult.
$converted = $result->getResult();
$parts = $converted instanceof MultiPartResult ? $converted->getContent() : [$converted];

foreach ($parts as $part) {
    if ($part instanceof McpListToolsResult) {
        echo \PHP_EOL.'MCP server "'.$part->getServerLabel().'" exposes '.count($part->getContent()).' tool(s).'.\PHP_EOL;
    }
    if ($part instanceof McpCallResult) {
        echo \PHP_EOL.'Called "'.$part->getName().'" on "'.$part->getServerLabel().'".'.\PHP_EOL;
    }
}
