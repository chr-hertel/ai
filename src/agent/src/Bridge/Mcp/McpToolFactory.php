<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Mcp;

use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Agent\Toolbox\ToolFactoryInterface;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Yields {@see Tool} metadata for every tool advertised by the MCP servers an
 * {@see McpClientAdapter} represents.
 *
 * Plugs into the agent's existing {@see \Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory}
 * alongside {@see \Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory},
 * so local `#[AsTool]` bridges and remote MCP servers compose in one toolbox.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpToolFactory implements ToolFactoryInterface
{
    public function getTool(object|string $reference): iterable
    {
        if (!$reference instanceof McpClientAdapter) {
            throw ToolException::invalidReference(\is_object($reference) ? $reference::class : $reference);
        }

        $client = $reference->getClient();
        $prefix = $client->getPrefix();

        foreach ($client->listTools() as $remote) {
            $inputSchema = $remote->inputSchema;
            if (isset($inputSchema['properties']) && $inputSchema['properties'] instanceof \stdClass) {
                $inputSchema['properties'] = [];
            }

            yield new Tool(
                new ExecutionReference(McpClientAdapter::class, $remote->name),
                $prefix.$remote->name,
                $remote->description ?? '',
                $inputSchema,
            );
        }
    }
}
