# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in the Agent component.

## Component Overview

The Agent component provides a framework for building AI agents that interact with users and perform tasks. It sits on top of the Platform component and optionally integrates with the Store component for memory capabilities.

## Architecture

The Agent component is built around a context-driven execution pipeline.

### Core Classes
- **Agent** (`src/Agent.php`): Main agent class, constructed with `name`, `instruction`,
  `tools`, `handoffs`, an optional `MessageStore` and a `Context`
- **AgentInterface** (`src/AgentInterface.php`): `call()` for a synchronous result and
  `run()` for an iterable `Execution`
- **SpeechAgent** (`src/SpeechAgent.php`): Agent decorator for speech interactions

### Context Pipeline
- **Context** (`src/Context/Context.php`): Typed collection of data objects (instructions,
  platform content, memories, …)
- **ContextProcessorInterface** (`src/Context/ContextProcessorInterface.php`): Strategy
  declaring `supportedTypes()`; built-in processors live under `src/Context/Processor/`
  (`InstructionProcessor`, `AttachmentProcessor`, `ToolProcessor`, `MemoryProcessor`)
- **AgentRequest/AgentResult/AgentContext** (`src/Context/`): Envelopes passed through
  the processor chain

### Execution Model
- **Execution** (`src/Execution/Execution.php`): Lazy iterable returned by `Agent::run()`
- **Runner** (`src/Execution/Runner.php`): Internal generator that drives the agent and
  yields `Progress`, `Interaction` and `Result` updates
- **ParallelExecution** + `Agent::runMany()`: Run several inputs and merge their streams

### Key Features
- **Memory** (`src/Memory/`): Conversation memory with embedding support, wired via
  `Context\Processor\MemoryProcessor`
- **Toolbox** (`src/Toolbox/`): Tool integration; the runner owns the tool-call loop
  via the pluggable `ToolExecutorInterface` (default `SequentialToolExecutor`)
- **Handoff** (`src/Handoff/`): First-class routing to specialized agents; configured
  on the `Agent` constructor via the `handoffs:` argument
- **Store** (`src/Store/`): Optional `MessageStoreInterface` to persist the conversation
  across calls; bundled `InMemoryStore` implementation
- **Bridge** (`src/Bridge/`): Third-party tool integrations (Brave, Tavily, Wikipedia, etc.)

## Development Commands

### Testing
```bash
# Run all tests
vendor/bin/phpunit

# Run specific test
vendor/bin/phpunit tests/AgentTest.php

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Code Quality
```bash
# Static analysis (from component directory)
vendor/bin/phpstan analyse

# Code style fixing (from monorepo root)
cd ../../.. && vendor/bin/php-cs-fixer fix src/agent/
```

## Component-Specific Architecture

### Context Processing Chain
1. The `Runner` builds an `AgentRequest` from the input, options and merged `Context`
2. Context processors run in order; each declares the context-item types it supports
3. The platform is invoked; tool calls drive the loop via `ToolExecutorInterface`
4. Handoffs may delegate to another agent via `yield from $target->run(...)`
5. Result-aware processors observe the final result; an optional `MessageStore`
   persists the conversation

### Built-in Context Processors
- **InstructionProcessor**: Injects the agent instruction as the system message
- **AttachmentProcessor**: Attaches platform content items (Document, Image, ...) to
  the latest user message
- **ToolProcessor**: Exposes the agent's tools to the platform invocation
- **MemoryProcessor**: Injects memories returned by `MemoryProviderInterface`s

### Memory Providers
- **StaticMemoryProvider**: Simple in-memory storage
- **EmbeddingProvider**: Vector-based semantic memory using the Store component

### Tool Integration
The Toolbox system enables function calling:
- Tools are auto-discovered via `#[AsTool]` attributes
- Fault-tolerant execution with error handling
- Event system for tool lifecycle management

## Dependencies

The Agent component depends on:
- **Platform component**: Required for AI model communication
- **Store component**: Optional, for embedding-based memory
- **Symfony components**: HttpClient, Serializer, PropertyAccess, Clock

## Testing Patterns

- Use `MockHttpClient` for HTTP mocking instead of response mocking
- Test processors independently from the main Agent class
- Use fixtures from `/fixtures` for multimodal content testing
- Prefer `$this->assert*` over `self::assert*` in tests

## Development Notes

- All new classes should have `@author` tags
- Use component-specific exceptions from `src/Exception/`
- Follow Symfony coding standards with `@Symfony` PHP CS Fixer rules
- The component is marked as experimental and subject to BC breaks