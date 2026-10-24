# Symfony AI - OpenTelemetry Bridge

Traces Symfony AI platform calls, agent runs, tool calls and retrievals with [OpenTelemetry](https://opentelemetry.io/),
following the [GenAI semantic conventions](https://github.com/open-telemetry/semantic-conventions-genai). The spans can
be sent to any OTLP backend, for example Langfuse, Arize Phoenix, Datadog, Grafana Tempo or Jaeger.

The bridge depends only on `open-telemetry/api`. Setting up the SDK, exporter and sampling is up to the application.

**This Bridge is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

## Installation

```bash
composer require symfony/ai-open-telemetry-bridge
```

**This repository is a READ-ONLY sub-tree split**. See
https://github.com/symfony/ai to create issues or submit pull requests.

## Resources

- [Documentation](https://symfony.com/doc/current/ai/bridges/open-telemetry.html)
- [Report issues](https://github.com/symfony/ai/issues) and
  [send Pull Requests](https://github.com/symfony/ai/pulls)
