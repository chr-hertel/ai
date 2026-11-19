Symfony AI - Telemetry Component
================================

The Telemetry component traces platform calls, agent runs, tool calls and retrievals with `OpenTelemetry`_. The spans
follow the `GenAI semantic conventions`_, so any backend that ingests OTLP understands them, for example `Langfuse`_,
`Arize Phoenix`_, Datadog, Grafana Tempo or Jaeger.

.. note::

    The GenAI semantic conventions are still in development. The component pins the version it implements in
    :class:`Symfony\\AI\\Telemetry\\SemanticConvention\\GenAiAttributes` and is marked as experimental
    until the conventions have a stable release.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-telemetry

The component only depends on ``open-telemetry/api``, which ships a no-op implementation. To export spans, install the
OpenTelemetry SDK and an exporter in your application:

.. code-block:: terminal

    $ composer require open-telemetry/sdk open-telemetry/exporter-otlp

Usage
-----

Each decorator wraps one Symfony AI service and takes an OpenTelemetry tracer::

    use OpenTelemetry\API\Globals;
    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Platform\Bridge\OpenAi\Factory;
    use Symfony\AI\Telemetry\Agent\TracingAgent;
    use Symfony\AI\Telemetry\Platform\TracingPlatform;
    use Symfony\AI\Telemetry\SemanticConvention\GenAiAttributes;

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

* :class:`Symfony\\AI\\Telemetry\\Platform\\TracingPlatform`: a ``chat`` or ``embeddings`` span per model
  invocation, with the requested and response model, request options, token usage and finish reason. For streamed
  results the span ends once the stream is consumed;
* :class:`Symfony\\AI\\Telemetry\\Agent\\TracingAgent`: an ``invoke_agent`` span covering the agent run;
* :class:`Symfony\\AI\\Telemetry\\Toolbox\\TracingToolbox`: an ``execute_tool`` span per tool call;
* :class:`Symfony\\AI\\Telemetry\\Store\\TracingRetriever`: a ``retrieval`` span around vectorizing the
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

.. warning::

    Captured content may contain personal data and is sent to your tracing backend. Only enable it for backends that
    are allowed to store that data.

To mask personal data before it is recorded, pass a
:class:`Symfony\\AI\\Telemetry\\ContentRedactorInterface` to the decorators. The redactor also masks
the messages of recorded exceptions, as provider and tool errors may repeat the input. The
:class:`Symfony\\AI\\Telemetry\\RegexContentRedactor` replaces email addresses and card numbers by
default::

    use Symfony\AI\Telemetry\RegexContentRedactor;

    $agent = new TracingAgent($agent, $tracer, captureContent: true, redactor: new RegexContentRedactor());

    // your own patterns, with a placeholder or a callback returning the replacement
    $redactor = new RegexContentRedactor([
        RegexContentRedactor::EMAIL => '[email]',
        '/\bSO-\d+\b/' => static fn (string $orderNumber): string => substr($orderNumber, 0, 3).'***',
    ]);

Only digit sequences that pass the Luhn check count as card numbers, so timestamps and order numbers stay readable.
An invalid pattern throws an exception when the redactor is created. If a pattern fails on some content, e.g. because
of invalid UTF-8, the whole value is recorded as ``[redaction failed]``.

Identifying Runs
~~~~~~~~~~~~~~~~

Every agent run has its own run ID in its :class:`Symfony\\AI\\Agent\\Run\\RunContext`. ``TracingAgent``
adds the trace ID to the run context. Several runs can share one trace, for example all agent calls of one HTTP
request or a sub-agent called by another agent. The final result has both values as ``run_id`` and ``trace_id``
metadata. The fields of the run context are recorded on the ``invoke_agent`` span: ``app.run_id``, ``app.release``,
``app.prompt.name``, ``app.prompt.version`` and every attribute as ``app.<name>``::

    use Symfony\AI\Agent\Run\RunContext;

    // without a run ID, the ID of the execution is the run ID
    $execution = $agent->call($messages, [
        'run_context' => new RunContext(release: 'v1.4.0', attributes: ['channel' => 'web']),
    ]);

    $runId = $execution->getMetadata()->get('run_id');
    $traceId = $execution->getMetadata()->get('trace_id');

An attribute can't overwrite the fields of the run context: an attribute named ``release`` doesn't change
``app.release``.

Guardrail interventions dispatched as :class:`Symfony\\AI\\Agent\\Event\\GuardrailTriggered` become span events of
the current span with the :class:`Symfony\\AI\\Telemetry\\EventListener\\GuardrailSpanListener`.

Recording the User
~~~~~~~~~~~~~~~~~~

To see which user a trace belongs to, pass an implementation of
:class:`Symfony\\AI\\Telemetry\\UserIdResolverInterface` to ``TracingPlatform`` and ``TracingAgent``. Its
identifier is recorded as ``user.id``, which backends like Langfuse and Arize Phoenix use to filter traces by user::

    use Symfony\AI\Telemetry\UserIdResolverInterface;

    final class CurrentUserIdResolver implements UserIdResolverInterface
    {
        public function __construct(
            private readonly Authentication $authentication,
        ) {
        }

        public function resolve(): ?string
        {
            return $this->authentication->getCurrentUser()?->getEmail();
        }
    }

    $agent = new TracingAgent($agent, $tracer, userIdResolver: new CurrentUserIdResolver($authentication));

If the resolver returns ``null``, ``TracingAgent`` records the user ID of the run context instead. Without a resolver,
no user ID is recorded, even if the run context has one.

.. warning::

    User identifiers are often email addresses, which are personal data. Only record them for backends that are
    allowed to store that data.

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

With ``capture_user: true``, the bundle records the identifier of the authenticated Symfony user. Without an
OpenTelemetry setup of your own, the bundle can also export to a :ref:`tracing backend <telemetry-exporting>`
directly. See :ref:`the AI Bundle documentation <ai-bundle-tracing>` for all options.

.. _telemetry-exporting:

Exporting the Spans
-------------------

Without an OpenTelemetry setup of your own, the
:class:`Symfony\\AI\\Telemetry\\Exporter\\OtlpTracerProviderFactory` creates a tracer provider that sends the
spans to a tracing backend with OTLP over HTTP::

    use Symfony\AI\Telemetry\Exporter\OtlpBackend;
    use Symfony\AI\Telemetry\Exporter\OtlpTracerProviderFactory;
    use Symfony\AI\Telemetry\SemanticConvention\GenAiAttributes;

    $tracerProvider = OtlpTracerProviderFactory::create(
        // "/v1/traces" is appended to the endpoint, the protocol is "http/protobuf" by default
        new OtlpBackend('https://otel.example.com', ['Authorization' => 'Bearer '.$token], 'http/json'),
        // resource attributes of the spans
        ['service.name' => 'shop'],
    );
    $tracer = $tracerProvider->getTracer('symfony/ai', schemaUrl: GenAiAttributes::SCHEMA_URL);

The spans are sent in batches. Call ``$tracerProvider->forceFlush()`` once the response is sent, for example on the
``kernel.terminate`` event. With an empty endpoint, the factory returns a no-op tracer provider.

To support another backend, implement :class:`Symfony\\AI\\Telemetry\\Exporter\\BackendInterface`. Its
``decorate()`` method can adapt the spans to the backend before they are sent.

.. _`OpenTelemetry`: https://opentelemetry.io/
.. _`GenAI semantic conventions`: https://github.com/open-telemetry/semantic-conventions-genai
.. _`Langfuse`: https://langfuse.com/
.. _`Arize Phoenix`: https://phoenix.arize.com/
