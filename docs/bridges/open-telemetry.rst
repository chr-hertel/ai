Symfony AI - OpenTelemetry Bridge
=================================

The OpenTelemetry bridge traces platform calls, agent runs, tool calls and retrievals with `OpenTelemetry`_. The spans
follow the `GenAI semantic conventions`_, so any backend that ingests OTLP understands them, for example `Langfuse`_,
`Arize Phoenix`_, Datadog, Grafana Tempo or Jaeger.

.. note::

    The GenAI semantic conventions are still in development. The bridge pins the version it implements in
    :class:`Symfony\\AI\\OpenTelemetryBridge\\SemanticConvention\\GenAiAttributes` and is marked as experimental
    until the conventions have a stable release.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-open-telemetry-bridge

The bridge only depends on ``open-telemetry/api``, which ships a no-op implementation. To export spans, install the
OpenTelemetry SDK and an exporter in your application:

.. code-block:: terminal

    $ composer require open-telemetry/sdk open-telemetry/exporter-otlp

Usage
-----

Each decorator wraps one Symfony AI service and takes an OpenTelemetry tracer::

    use OpenTelemetry\API\Globals;
    use Symfony\AI\Agent\Agent;
    use Symfony\AI\OpenTelemetryBridge\Agent\TracingAgent;
    use Symfony\AI\OpenTelemetryBridge\Platform\TracingPlatform;
    use Symfony\AI\OpenTelemetryBridge\SemanticConvention\GenAiAttributes;
    use Symfony\AI\Platform\Bridge\OpenAi\Factory;

    $tracer = Globals::tracerProvider()->getTracer('symfony/ai', schemaUrl: GenAiAttributes::SCHEMA_URL);

    $platform = new TracingPlatform(Factory::createPlatform($apiKey), $tracer, 'openai');
    $agent = new TracingAgent(new Agent($platform, 'gpt-4o-mini', name: 'support'), $tracer);

An agent run with one tool call then produces this span tree:

.. code-block:: text

    invoke_agent support
    ├── chat gpt-4o-mini
    ├── execute_tool clock
    └── chat gpt-4o-mini

The following decorators are available:

* :class:`Symfony\\AI\\OpenTelemetryBridge\\Platform\\TracingPlatform`: a ``chat`` or ``embeddings`` span per model
  invocation, with the requested and response model, request options, token usage and finish reason. For streamed
  results the span ends once the stream is consumed;
* :class:`Symfony\\AI\\OpenTelemetryBridge\\Agent\\TracingAgent`: an ``invoke_agent`` span covering the agent run;
* :class:`Symfony\\AI\\OpenTelemetryBridge\\Toolbox\\TracingToolbox`: an ``execute_tool`` span per tool call;
* :class:`Symfony\\AI\\OpenTelemetryBridge\\Store\\TracingRetriever`: a ``retrieval`` span around vectorizing the
  query and searching the store.

Spans of a message bag also carry its identifier as ``gen_ai.conversation.id``, which backends like Langfuse use to
group the runs of one conversation into a session.

Instrumentation never changes the behavior of the call: exceptions are recorded on the span and rethrown unchanged,
and a failure of the tracing itself never reaches your code.

Capturing Content
~~~~~~~~~~~~~~~~~

Prompts, completions, tool arguments and tool results are not recorded by default. Pass ``true`` as the
``$captureContent`` argument of a decorator to record them::

    $platform = new TracingPlatform($platform, $tracer, 'openai', captureContent: true);

.. caution::

    Captured content may contain personal data and is sent to your tracing backend. Only enable it for backends that
    are allowed to store that data.

Symfony Integration
-------------------

With the AI Bundle, enable tracing in its configuration and every platform, agent, toolbox and retriever gets
decorated:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        tracing:
            enabled: true
            # service ID of the tracer provider of your OpenTelemetry setup, the global one when omitted
            tracer_provider: 'app.tracer_provider'

See :ref:`the AI Bundle documentation <ai-bundle-tracing>` for all options.

Exporting to Langfuse
---------------------

`Langfuse`_ ingests OTLP over HTTP on ``/api/public/otel``, authenticated with the project's public and secret key.
It does not support gRPC. With the PHP SDK, use the JSON protocol and disable response compression, as the PSR-18
client of ``symfony/http-client`` already decodes compressed responses::

    use OpenTelemetry\API\Common\Time\Clock;
    use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
    use OpenTelemetry\Contrib\Otlp\SpanExporter;
    use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
    use OpenTelemetry\SDK\Trace\TracerProvider;

    $transport = (new OtlpHttpTransportFactory())->create(
        'https://cloud.langfuse.com/api/public/otel/v1/traces',
        'application/json',
        [
            'Authorization' => 'Basic '.base64_encode($publicKey.':'.$secretKey),
            'Accept-Encoding' => 'identity',
        ],
    );

    $tracerProvider = TracerProvider::builder()
        ->addSpanProcessor(new BatchSpanProcessor(new SpanExporter($transport), Clock::getDefault()))
        ->build();

Call ``$tracerProvider->forceFlush()`` once the response is sent, for example on the ``kernel.terminate`` event.

.. _`OpenTelemetry`: https://opentelemetry.io/
.. _`GenAI semantic conventions`: https://github.com/open-telemetry/semantic-conventions-genai
.. _`Langfuse`: https://langfuse.com/
.. _`Arize Phoenix`: https://phoenix.arize.com/
