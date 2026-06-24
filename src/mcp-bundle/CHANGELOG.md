CHANGELOG
=========

0.11
----

 * Add first-class MCP Apps support: the `#[AsMcpApp]` attribute registers an interactive HTML UI
   resource (including the `_meta.ui` descriptor marker), registers the linked tool from a handler
   method (`render` by default) with its `ui` link auto-set to the app, and auto-enables the MCP Apps
   server extension; configurable via `mcp.apps.enabled`
 * Add the `@Mcp/app/base.html.twig` base template carrying the MCP Apps postMessage bridge, and a
   Twig-backed renderer so template apps need no handler code
 * Add an HTML-over-the-wire path for MCP Apps: the base template's default `render(model)` injects an
   `html` model (or the `#root` element) so a tool can return server-rendered Twig markup with no
   client-side DOM building; `McpAppRenderer::renderFragment()` renders a Twig template to that HTML string

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
