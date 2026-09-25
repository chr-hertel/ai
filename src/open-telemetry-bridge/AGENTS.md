# AGENTS.md

AI agent guidance for the OpenTelemetry bridge.

## Bridge Overview

Decorators that turn Symfony AI calls into OpenTelemetry spans following the GenAI semantic conventions. Depends on
`open-telemetry/api` only; the SDK is a dev dependency for the in-memory exporter tests.

## Architecture

- **TracingPlatform** (`src/Platform/`): `chat`/`embeddings` CLIENT span per invocation, ended on result conversion or
  stream completion by `InferenceSpan`, with a destructor fallback for results that are never read
- **TracingAgent** (`src/Agent/`): `invoke_agent` span around the lazy `Execution`, active only while the agent advances
- **TracingToolbox** (`src/Toolbox/`): `execute_tool` span per tool call
- **TracingRetriever** (`src/Store/`): `retrieval` span around vectorizing and querying
- **SemanticConvention** (`src/SemanticConvention/`): every attribute name and the span naming. The conventions are
  still in development, so a version bump must stay a change in this namespace only
- **Guard**: instrumentation failures never reach the caller; business exceptions are recorded and rethrown unchanged

## Essential Commands

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## Development Notes

- Never put ids, user input or URLs into span names
- Token usage belongs on inference spans only, never on `invoke_agent`
- Content (prompts, completions, tool arguments and results) is only captured with `$captureContent`
