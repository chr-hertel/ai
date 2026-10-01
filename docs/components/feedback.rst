Symfony AI - Feedback Component
===============================

The Feedback component records how well agent runs went: explicit feedback of users like a thumbs up or down,
implicit signals like failed tool calls or guardrail interventions, and scores of automated evaluators. Every piece of
feedback refers to a run by the run ID of the agent's :class:`Symfony\\AI\\Agent\\Context\\RunContext`, which is the
trace ID when the agent is traced with the :doc:`OpenTelemetry bridge </bridges/open-telemetry>`. Feedback therefore
attaches to the trace in tracing backends like Langfuse.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-feedback

Recording Feedback
------------------

The agent returns the run ID with its result, the frontend sends it back together with the rating::

    use Symfony\AI\Feedback\Feedback;
    use Symfony\AI\Feedback\Signal;

    $runId = $agent->call($messages)->getMetadata()->get('run_id');

    // later, in the feedback endpoint
    $recorder->record(new Feedback($runId, Signal::Thumbs, false, comment: 'The refund was not opened.'));

Recorders implement :class:`Symfony\\AI\\Feedback\\FeedbackRecorderInterface`:

* :class:`Symfony\\AI\\Feedback\\InMemoryRecorder` keeps feedback in memory, for tests;
* :class:`Symfony\\AI\\Feedback\\Bridge\\Langfuse\\LangfuseRecorder` sends feedback as scores on the Langfuse trace;
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
