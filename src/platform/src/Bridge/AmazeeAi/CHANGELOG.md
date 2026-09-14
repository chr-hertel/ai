CHANGELOG
=========

0.14
----

 * [BC BREAK] Replace the model clients and result converters with a `ChatCompletionsClient` extending the Generic one and the Generic `EmbeddingsClient`

0.8
---

 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.7
---

 * [BC BREAK] Streaming completion responses now yield typed deltas from the Generic completions converter (`TextDelta`, `ThinkingDelta`, `ThinkingComplete`, `ToolCallStart`, `ToolInputDelta`, `ToolCallComplete`, `TokenUsage`)

0.4
---

 * Add the bridge
