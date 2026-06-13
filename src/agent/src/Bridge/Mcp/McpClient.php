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

use Mcp\Client;
use Mcp\Client\Transport\TransportInterface;
use Mcp\Exception\ExceptionInterface as McpSdkExceptionInterface;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool as McpTool;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ConnectionException;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ToolCallException;

/**
 * Server-bound wrapper around the official MCP {@see Client}.
 *
 * Owns the connection lifecycle (lazy connect, idempotent disconnect) and
 * paginates the server's tools/list response so the bridge can present every
 * remote tool to the agent in one call.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpClient
{
    private bool $connected = false;

    public function __construct(
        private readonly string $name,
        private readonly Client $client,
        private readonly TransportInterface $transport,
        private readonly string $prefix = '',
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrefix(): string
    {
        return '' !== $this->prefix ? $this->prefix : $this->name.'_';
    }

    /**
     * @return list<McpTool>
     */
    public function listTools(): array
    {
        $this->connect();

        $tools = [];
        $cursor = null;

        do {
            try {
                $page = $this->client->listTools($cursor);
            } catch (McpSdkExceptionInterface $e) {
                throw ToolCallException::listFailed($this->name, $e);
            }

            foreach ($page->tools as $tool) {
                $tools[] = $tool;
            }

            $cursor = $page->nextCursor;
        } while (null !== $cursor);

        return $tools;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function callTool(string $name, array $arguments): CallToolResult
    {
        $this->connect();

        try {
            return $this->client->callTool($name, $arguments);
        } catch (McpSdkExceptionInterface $e) {
            throw ToolCallException::callFailed($this->name, $name, $e);
        }
    }

    public function disconnect(): void
    {
        if (!$this->connected) {
            return;
        }

        $this->client->disconnect();
        $this->connected = false;
    }

    private function connect(): void
    {
        if ($this->connected) {
            return;
        }

        try {
            $this->client->connect($this->transport);
        } catch (McpSdkExceptionInterface $e) {
            throw ConnectionException::failed($this->name, $e);
        }

        $this->connected = true;
    }
}
