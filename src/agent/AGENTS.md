# AGENTS.md

AI agent guidance for the Agent component.

## Component Overview

Framework for building AI agents with user interaction and task execution. Built on Platform component with optional Store integration for memory.

## Architecture

The component is built around a context-driven execution pipeline.

### Core Classes
- **Agent** (`src/Agent.php`): Main agent class, constructed with `name`, `instruction`, `tools`, `handoffs`, an optional `MessageStore` and a `Context`
- **AgentInterface**: `call()` for a synchronous result, `run()` for an iterable `Execution`
- **SpeechAgent** (`src/SpeechAgent.php`): Agent decorator for speech interactions

### Context Pipeline
- **Context** (`src/Context/Context.php`): Typed collection of data objects (instructions, platform content, memories, …)
- **ContextProcessorInterface**: Strategy declaring `supportedTypes()`; built-in processors in `src/Context/Processor/`
- **AgentRequest/AgentResult/AgentContext** (`src/Context/`): Envelopes passed through the processor chain

### Execution Model
- **Execution** (`src/Execution/Execution.php`): Lazy iterable returned by `Agent::run()`
- **Runner** (`src/Execution/Runner.php`): Internal generator yielding `Progress`, `Interaction` and `Result` updates
- **ParallelExecution** + `Agent::runMany()`: Run several inputs and merge their streams

### Key Features
- **Memory** (`src/Memory/`): Conversation memory with embeddings, wired via `Context\Processor\MemoryProcessor`
- **Toolbox** (`src/Toolbox/`): Function calling; the runner owns the tool-call loop via `ToolExecutorInterface` (default `SequentialToolExecutor`)
- **Handoff** (`src/Handoff/`): First-class routing to specialized agents via the `handoffs:` argument
- **Store** (`src/Store/`): Optional `MessageStoreInterface` to persist conversations across calls
- **Bridge** (`src/Bridge/`): Third-party tool integrations (Brave, Tavily, Wikipedia, etc.)

## Essential Commands

### Testing
```bash
vendor/bin/phpunit
vendor/bin/phpunit tests/AgentTest.php
vendor/bin/phpunit --coverage-html coverage/
```

### Code Quality
```bash
vendor/bin/phpstan analyse
cd ../../.. && vendor/bin/php-cs-fixer fix src/agent/
```

## Processing Architecture

### Pipeline Flow
1. The `Runner` builds an `AgentRequest` from input, options and merged `Context`
2. Context processors run in order; each declares the context-item types it supports
3. The platform is invoked; tool calls drive the loop via `ToolExecutorInterface`
4. Handoffs may delegate to another agent via `yield from $target->run(...)`
5. Result-aware processors observe the final result; an optional `MessageStore` persists the conversation

### Built-in Context Processors
- **InstructionProcessor**: Injects the agent instruction as the system message
- **AttachmentProcessor**: Attaches platform content items (Document, Image, …) to the latest user message
- **ToolProcessor**: Exposes the agent's tools to the platform invocation
- **MemoryProcessor**: Injects memories returned by `MemoryProviderInterface`s

### Memory Providers
- **StaticMemoryProvider**: In-memory storage
- **EmbeddingProvider**: Vector-based semantic memory (requires Store)

### Tool Integration
- Auto-discovery via `#[AsTool]` attributes
- Fault-tolerant execution
- Event system for lifecycle management

## Dependencies

- **Platform component**: Required for AI communication
- **Store component**: Optional for embedding memory
- **Symfony**: HttpClient, Serializer, PropertyAccess, Clock

## Testing Patterns

- Use `MockHttpClient` over response mocking
- Test processors independently
- Use `/fixtures` for multimodal content
- Prefer `$this->assert*` over `self::assert*` in tests

## Development Notes

- Component is experimental (BC breaks possible)
- Add `@author` tags to new classes
- Use component-specific exceptions from `src/Exception/`
- Follow `@Symfony` PHP CS Fixer rules
