CHANGELOG
=========

0.9
---

 * Add `clients:` configuration to register outbound MCP clients (`Mcp\Client`) over
   stdio or HTTP transports as DI services tagged `mcp.client`
 * Add `mcp:client:debug <name>` console command to inspect a configured client
   (server info, instructions, advertised tools)

0.8
---

 * Add `framework` session store backed by Symfony's `SessionHandlerInterface`

0.4
---

 * Add `ResetInterface` support to `TraceableRegistry` to clear collected data between requests

0.3
---

 * Add support for server description, icons, and website URL

0.1
---

 * Add the bundle
