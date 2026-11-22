# AGENTS.md

AI agent guidance for the Telemetry component.

## Component Overview

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
- **Exporter** (`src/Exporter/`): `BackendInterface` (endpoint, headers, protocol, resource attributes, exporter
  decorator), the generic `OtlpBackend` and `OtlpTracerProviderFactory`, which needs `open-telemetry/sdk` and
  `open-telemetry/exporter-otlp`
- **Bridges** (`src/Bridge/`): one `BackendInterface` per tracing backend, `Langfuse`, `Phoenix`, `Jaeger` and `Tempo`.
  Backend quirks live there, never in the decorators

## Essential Commands

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## Development Notes

- Never put ids, user input or URLs into span names
- Token usage belongs on inference spans only, never on `invoke_agent`
- Content (prompts, completions, tool arguments and results) is only captured with `$captureContent`
- `user.id` is only recorded with a `UserIdResolverInterface`, on inference and agent spans
- The decorators emit the GenAI semantic conventions only. `Bridge\Phoenix\OpenInferenceSpanExporter` derives `input.value`/`output.value` of agent spans at export time for the session view of Arize Phoenix; remove it once Phoenix derives them from `gen_ai.*.messages`
