MCP Client AI Tool
==================

Exposes tools advertised by a remote [Model Context Protocol](https://modelcontextprotocol.io)
server to a Symfony AI `Agent` via the official `mcp/sdk` client.

The bridge wraps one connected `Mcp\Client` per MCP server and yields a
`Symfony\AI\Platform\Tool\Tool` for every entry returned by `tools/list`. Each
tool invocation is forwarded to the server through `tools/call` — the agent
sees them just like any local `#[AsTool]` method.

Tools surfaced to the model are prefixed by the server's short name (e.g. a
`read_file` tool from a server named `filesystem` becomes `filesystem_read_file`)
to avoid collisions when an agent uses several MCP servers at once.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
