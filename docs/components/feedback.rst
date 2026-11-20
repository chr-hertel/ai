Symfony AI - Feedback Component
===============================

The Feedback component records how well agent runs went: explicit feedback of users like a thumbs up or down,
implicit signals like failed tool calls or guardrail interventions, and scores of automated evaluators. Every piece of
feedback refers to a run by the run ID of the agent's :class:`Symfony\\AI\\Agent\\Run\\RunContext`. When the
agent is traced with the :doc:`Telemetry component </components/telemetry>`, the feedback also carries the trace
ID, so tracing backends like Langfuse can show it on the trace.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-feedback

Recording Feedback
------------------

The agent returns the run ID and the trace ID with its result. The frontend sends them back together with the
rating::

    use Symfony\AI\Feedback\Feedback;
    use Symfony\AI\Feedback\Signal;

    $metadata = $agent->call($messages)->getMetadata();
    $runId = $metadata->get('run_id');
    $traceId = $metadata->get('trace_id');

    // later, in the feedback endpoint
    $recorder->record(new Feedback($runId, Signal::Thumbs, false, comment: 'The refund was not opened.', traceId: $traceId, id: $feedbackId));

The optional ``id`` is chosen by the frontend, e.g. a UUID per rating. When the frontend sends the same rating again,
the backend updates the score instead of adding a second one.

Recorders implement :class:`Symfony\\AI\\Feedback\\FeedbackRecorderInterface`:

* :class:`Symfony\\AI\\Feedback\\InMemoryRecorder` keeps feedback in memory, for tests;
* :class:`Symfony\\AI\\Feedback\\Bridge\\Langfuse\\LangfuseRecorder` sends feedback as scores on the Langfuse trace,
  it throws an exception for feedback without a trace ID. Install it with
  ``composer require symfony/ai-langfuse-feedback-recorder``;
* :class:`Symfony\\AI\\Feedback\\ChainRecorder` records with several recorders, a failing one does not stop the others;
* :class:`Symfony\\AI\\Feedback\\Messenger\\MessengerRecorder` dispatches feedback to a message bus, so recording never
  blocks a request; :class:`Symfony\\AI\\Feedback\\Messenger\\RecordFeedbackHandler` records it on the worker.

Implicit Signals
----------------

Two event listeners derive signals from agent runs:

* :class:`Symfony\\AI\\Feedback\\EventListener\\ToolErrorSignalListener` records failed tool calls of a run, listening
  to :class:`Symfony\\AI\\Agent\\Toolbox\\Event\\ToolCallsExecuted`;
* :class:`Symfony\\AI\\Feedback\\EventListener\\GuardrailSignalListener` records guardrail interventions, like a denied
  tool call or a run stopped by its budget, listening to :class:`Symfony\\AI\\Agent\\Event\\GuardrailTriggered`.

Both listeners log a failing recorder as a warning, so an unavailable feedback backend never breaks the agent run.

.. note::

    Messenger retries a failed message only if its handler throws. Give
    :class:`Symfony\\AI\\Feedback\\Messenger\\RecordFeedbackHandler` the backend recorder itself, not a
    ``ChainRecorder``, which logs errors and doesn't throw.
