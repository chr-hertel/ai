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

use Mcp\Schema\Content\TextContent;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ToolCallException;
use Symfony\AI\Agent\Toolbox\ExecutableToolInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Adapter exposing one MCP server's tools as a single executable target.
 *
 * One instance of this class represents the N remote tools advertised by a
 * single MCP server. Each {@see Tool} metadata produced by {@see McpToolFactory}
 * carries the *remote* tool name in its {@see ExecutionReference::method};
 * {@see self::execute()} retrieves that name and forwards the call.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpClientAdapter implements ExecutableToolInterface
{
    public function __construct(
        private readonly McpClient $client,
    ) {
    }

    public function getClient(): McpClient
    {
        return $this->client;
    }

    public function execute(Tool $metadata, ToolCall $toolCall): mixed
    {
        $remoteName = $metadata->getReference()->getMethod();

        $result = $this->client->callTool($remoteName, $toolCall->getArguments());

        if (null !== $result->structuredContent) {
            $payload = $result->structuredContent;
        } else {
            $payload = $this->renderContent($result->content);
        }

        if ($result->isError) {
            if (\is_string($payload)) {
                $detail = $payload;
            } else {
                $encoded = json_encode($payload, \JSON_UNESCAPED_SLASHES);
                $detail = false !== $encoded ? $encoded : 'unknown error';
            }

            throw ToolCallException::returnedError($this->client->getName(), $remoteName, $detail);
        }

        return $payload;
    }

    /**
     * @param list<object> $content
     *
     * @return string|array{text: string, content: list<object>}
     */
    private function renderContent(array $content): string|array
    {
        $textChunks = [];
        $other = [];

        foreach ($content as $item) {
            if ($item instanceof TextContent) {
                $textChunks[] = \is_string($item->text) ? $item->text : json_encode($item->text, \JSON_UNESCAPED_SLASHES);
                continue;
            }

            $other[] = $item;
        }

        if ([] === $other) {
            return implode("\n", $textChunks);
        }

        return [
            'text' => implode("\n", $textChunks),
            'content' => $content,
        ];
    }
}
