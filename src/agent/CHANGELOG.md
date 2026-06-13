CHANGELOG
=========

1.0
---

 * [BC BREAK] Rework `AgentInterface`: `call()` now accepts `string|MessageBag|UserMessage` plus a `Context`, and a new `run()` method returns a lazy, iterable `Execution`
 * [BC BREAK] Remove `AgentInterface::getModel()`; the model is configured on the `Agent` constructor and overridable via the `model` option
 * [BC BREAK] Change the `Agent` constructor to first-class named arguments: `name`, `instruction`, `tools`, `handoffs`, `model`, `store`, `context`, `contextProcessors`
 * [BC BREAK] Remove `InputProcessorInterface`, `OutputProcessorInterface`, `Input`, `Output`, `AgentAwareInterface`, `AgentAwareTrait`, `SystemPromptInputProcessor`, `ModelOverrideInputProcessor`, `MemoryInputProcessor`, `Toolbox\AgentProcessor`, `Toolbox\StreamListener`, `Attribute\AsInputProcessor`, `Attribute\AsOutputProcessor` and the entire `MultiAgent` namespace; the new context processor system supersedes them
 * [BC BREAK] Change `MemoryProviderInterface::load()` to take an `AgentRequest` instead of the removed `Input`
 * Add a first-class `Context` of data objects processed by per-type `ContextProcessorInterface` strategies, with built-in `InstructionProcessor`, `AttachmentProcessor`, `ToolProcessor` and `MemoryProcessor`
 * Add the `Execution` model with `Progress`, `Interaction` and `Result` updates for progress reporting and human-in-the-loop, plus a `ParallelExecution` and `Agent::runMany()` to drive several inputs at once
 * Add agent-level events (`AgentInvocationStarted`, `ModelRequested`, `ModelResponded`, `HandoffRequested`, `HandoffCompleted`, `AgentInvocationCompleted`) dispatched via an optional event dispatcher set with `Agent::setEventDispatcher()`
 * Add first-class handoffs (`Handoff`, `HandoffResolver`) and a pluggable `ToolExecutorInterface` (default `SequentialToolExecutor`)
 * Add a `Store` namespace (`MessageStoreInterface`, `ManagedStoreInterface`, `InMemoryStore`); when a store is passed to the `Agent` constructor the runner loads, appends and persists the conversation across calls

0.10
----

 * Add `SystemPromptInputProcessor::getSystemPrompt()` to read the configured system prompt without reflection

0.8
---

 * [BC BREAK] Reduce visibility of `SimilaritySearch::$usedDocuments` to `private`; use `getUsedDocuments()` instead
 * [BC BREAK] Change `public array $calls` to `private array $calls` in `TraceableAgent` and `TraceableToolbox` - use `getCalls()` instead
 * [BC BREAK] Change `StaticMemoryProvider` constructor from variadic `string ...$memory` to `array $memory`
 * [BC BREAK] Change `ToolCallsExecuted` constructor from variadic `ToolResult ...$toolResults` to `array $toolResults`

0.7
---

 * Add `TraceableAgent` and `TraceableToolbox` profiler decorators moved from AI Bundle
 * [BC BREAK] Change `SimilaritySearch` to use `RetrieverInterface` instead of `VectorizerInterface` and `StoreInterface`
 * Add customizable `$promptTemplate` parameter to `SimilaritySearch` constructor
 * [BC BREAK] Remove `AbstractToolFactory` in favor of standalone `ReflectionToolFactory` and `MemoryToolFactory`
 * [BC BREAK] Change `ToolFactoryInterface::getTool()` signature from `string $reference` to `object|string $reference`
 * Add `ToolCallRequested` event dispatched before tool execution
 * Update `StreamListener` to use `DeltaEvent` and `TextDelta` instead of `ChunkEvent` and raw strings
 * Update `StreamListener` to react to `ToolCallComplete` instead of `ToolCallResult`
 * Add `ValidateToolCallArgumentsListener` to validate tool call arguments with `symfony/validator` component
 * Add `SpeechAgent` decorator for speech-to-text and text-to-speech capabilities

0.4
---

 * [BC BREAK] Rename `Symfony\AI\Agent\Toolbox\Tool\Agent` to `Symfony\AI\Agent\Toolbox\Tool\Subagent`
 * [BC BREAK] Change AgentProcessor `keepToolMessages` to `excludeToolMessages` and default behaviour to preserve tool messages
 * Add `MetaDataAwareTrait` to `MockResponse`, the metadata will also be set on the returned `TextResult` when calling the `toResult` function
 * Add `HasSourcesTrait` to `Symfony\AI\Agent\Toolbox\Tool\Subagent`

0.3
---

 * [BC BREAK] Drop toolboxes `StreamResult` in favor of `StreamListener` on top of Platform's `StreamResult`
 * [BC BREAK] Rename `SourceMap` to `SourceCollection`, its methods from `getSources()` to `all()` and `addSource()` to `add()`
 * [BC BREAK] Third Argument of `ToolResult::__construct()` now expects `SourceCollection` instead of `array<int, Source>`
 * Add `maxToolCalls` parameter to `AgentProcessor` to limit tool calling iterations and prevent infinite loops
 * Add `Countable` and `IteratorAggregate` implementations to `SourceCollection`

0.2
---

 * [BC BREAK] Switch `MemoryInputProcessor` to use `iterable` instead of variadic arguments

0.1
---

 * Add the component
