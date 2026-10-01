# Symfony AI - Feedback Component

Records explicit feedback (thumbs, ratings, corrections) and implicit signals (tool errors, guardrails, regenerated
answers) against agent runs. A run is identified by the run ID of the agent's `RunContext`, which equals the trace ID
when the run is traced with the OpenTelemetry bridge, so feedback attaches to the trace in tracing backends like
Langfuse.

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

## Installation

```bash
composer require symfony/ai-feedback
```

## Resources

- [Documentation](https://symfony.com/doc/current/ai/components/feedback.html)
- [Report issues](https://github.com/symfony/ai/issues) and
  [send Pull Requests](https://github.com/symfony/ai/pulls)
  in the [main Symfony AI repository](https://github.com/symfony/ai)
