CHANGELOG
=========

0.8
---

 * [BC BREAK] `MessageNormalizer` now serializes assistant messages with an ordered `parts` field to preserve the sequence of `Text`, `Thinking`, and `ToolCall` blocks across round-trips. Legacy payloads (no `parts`, only `content` + `toolsCalls`) still denormalize, but ordering is no longer guaranteed for them.

0.7
---

 * Add `TraceableChat` and `TraceableMessageStore` profiler decorators moved from AI Bundle
 * Add `ChatInterface::stream()` method for real-time streaming support

0.4
---

 * Add `ResetInterface` support to in-memory store

0.1
---

 * Add the component
 * Add `metadata` from `TextResult` to `AssistantMessage`
