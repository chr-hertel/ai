.. card:
    title: Closing the Loop
    description: Trace production runs, collect feedback, turn failures into eval cases and gate releases on them.
    icon: refresh
    components: Agent, Platform, OpenTelemetry Bridge, Feedback, Eval

Close the Loop between Production, Feedback and Evaluation
==========================================================

Shipping an agent is the start of the work. To make it better, and to keep it from getting worse, you need a loop:
production runs are traced and rated, the bad ones become eval cases, and every change to a prompt or model has to
pass those cases before it is released:

.. code-block:: text

     production traffic ──► traces + feedback ──► triage ──► eval dataset
            ▲                                                     │
            └──────── release ◄── gate in CI ◄── eval suite ◄─────┘

In this guide you will build that loop for a customer support agent of a web shop. It looks up orders and opens
refunds, and one of its prompt versions promises refunds without opening them. You will find that failure in
production feedback, capture it as eval cases, and prove that the next prompt version fixes it without breaking
anything else.

The complete, runnable version of this guide is in ``examples/closing-the-loop/``. It uses a scripted model, so it
runs offline and gives the same result every time.

Prerequisites
-------------

* Symfony AI Agent and Platform components
* An OpenTelemetry backend like Langfuse, Arize Phoenix, Grafana Tempo or Jaeger, optional for this guide
* An API key of a supported platform

Step 1: Install Packages
------------------------

Install the Agent component, the OpenTelemetry bridge and the Feedback component, plus the Eval component for
development:

.. code-block:: terminal

    $ composer require symfony/ai-agent symfony/ai-open-telemetry-bridge symfony/ai-feedback
    $ composer require --dev symfony/ai-eval symfony/yaml

Step 2: Version the Prompt
--------------------------

When a regression shows up, you need to know which prompt produced it. Keep the prompt versions in a YAML file
next to your code:

.. code-block:: yaml

    # prompts/support.yaml
    name: support/system
    versions:
        '2026-08-v2':
            template: |
                You are the support assistant of {shop_name}.
                Always look up the order before discussing it.
        '2026-09-v3':
            template: |
                You are the support assistant of {shop_name}.
                Always look up the order before discussing it.
                Never promise a refund before open_refund returned a ticket id.

The prompt registry resolves the instruction for every run. With a rollout selector, the new version reaches a share
of the conversations first, and every run records the version it used::

    use Symfony\AI\Agent\Event\AgentInvocationStarted;
    use Symfony\AI\Agent\Prompt\PercentageRolloutSelector;
    use Symfony\AI\Agent\Prompt\PromptInstructionListener;
    use Symfony\AI\Agent\Prompt\YamlPromptRegistry;
    use Symfony\Component\EventDispatcher\EventDispatcher;

    $prompts = new YamlPromptRegistry([__DIR__.'/prompts']);

    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(AgentInvocationStarted::class, new PromptInstructionListener(
        $prompts,
        'support/system',
        version: '2026-08-v2',
        variables: ['shop_name' => 'ACME'],
        selector: new PercentageRolloutSelector('support/system', '2026-09-v3', '2026-08-v2', 50),
    ));

Step 3: Trace Every Run
-----------------------

The OpenTelemetry bridge wraps the platform, the toolbox and the agent. It identifies each run by its trace ID, so
everything you learn about a run later, like a thumbs down, attaches to its trace. Captured content is masked before
it leaves the application::

    use OpenTelemetry\API\Globals;
    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\OpenTelemetryBridge\Agent\TracingAgent;
    use Symfony\AI\OpenTelemetryBridge\Platform\TracingPlatform;
    use Symfony\AI\OpenTelemetryBridge\RegexContentRedactor;
    use Symfony\AI\OpenTelemetryBridge\Toolbox\TracingToolbox;

    $tracer = Globals::tracerProvider()->getTracer('symfony/ai');
    $redactor = new RegexContentRedactor();

    $platform = new TracingPlatform($anthropicPlatform, $tracer, 'anthropic', true, redactor: $redactor);
    $toolbox = new TracingToolbox(new FaultTolerantToolbox(new Toolbox([new ShopTools()], eventDispatcher: $dispatcher)), $tracer, true, $redactor);

    $agent = new TracingAgent(
        new Agent($platform, 'claude-sonnet-4-5', name: 'support', toolbox: $toolbox, eventDispatcher: $dispatcher),
        $tracer,
        captureContent: true,
        redactor: $redactor,
    );

With the AI Bundle, the ``tracing`` configuration wires the same decorators for every platform, agent and toolbox,
see :doc:`/bundles/ai-bundle`.

Describe each run with a run context. Its run ID stays empty, so the tracing decorator fills in the trace ID, and
your controller returns it with the answer::

    use Symfony\AI\Agent\Context\Context;
    use Symfony\AI\Agent\Context\RunContext;

    $execution = $agent->call($question, new Context(new RunContext(
        '',
        $user->getUserIdentifier(),
        $release,
        attributes: ['channel' => 'web', 'conversation' => $conversationId],
    )));

    return new JsonResponse([
        'answer' => $execution->getContent(),
        'run_id' => $execution->getMetadata()->get('run_id'),
    ]);

The ``invoke_agent`` span now carries ``app.run_id``, ``app.release``, ``app.prompt.version`` and your attributes, so
your tracing backend can compare prompt versions side by side.

Step 4: Add Guardrails and a Budget
-----------------------------------

Guardrails stop known failures before they reach the customer. Deny refunds outside the refund window before the
tool runs, and report the intervention::

    use Symfony\AI\Agent\Event\GuardrailTriggered;
    use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;

    $dispatcher->addListener(ToolCallRequested::class, function (ToolCallRequested $event) use ($dispatcher, $policy) {
        $orderNumber = $event->getToolCall()->getArguments()['orderNumber'] ?? '';

        if ('open_refund' !== $event->getDefinition()->getName() || $policy->isRefundable($orderNumber)) {
            return;
        }

        $event->deny('This order is outside the refund window. Offer to escalate to a human instead.');
        $dispatcher->dispatch(new GuardrailTriggered('refund_policy', GuardrailTriggered::ACTION_DENIED, runContext: $event->getRunContext()));
    });

A run budget ends runaway tool-calling loops gracefully, instead of letting them burn tokens::

    use Symfony\AI\Agent\Budget\RunBudget;
    use Symfony\AI\OpenTelemetryBridge\EventListener\GuardrailSpanListener;

    $budget = new RunBudget(maxTokens: 8_000, eventDispatcher: $dispatcher);
    foreach (RunBudget::getSubscribedEvents() as $event => $method) {
        $dispatcher->addListener($event, [$budget, $method]);
    }

    $dispatcher->addListener(GuardrailTriggered::class, new GuardrailSpanListener());

Every intervention is now an event on the span of the run that triggered it.

Step 5: Collect Feedback
------------------------

Traces tell you what happened, feedback tells you whether it was good. Record explicit feedback from your frontend
with the run ID it received, and derive implicit signals from failed tool calls and guardrails::

    use Symfony\AI\Feedback\Bridge\Langfuse\LangfuseRecorder;
    use Symfony\AI\Feedback\ChainRecorder;
    use Symfony\AI\Feedback\EventListener\GuardrailSignalListener;
    use Symfony\AI\Feedback\EventListener\ToolErrorSignalListener;
    use Symfony\AI\Feedback\Feedback;
    use Symfony\AI\Feedback\Signal;
    use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;

    $recorder = new ChainRecorder([
        new LangfuseRecorder($httpClient, $langfuseHost, $publicKey, $secretKey),
        $localRecorder,
    ]);

    $dispatcher->addListener(ToolCallsExecuted::class, new ToolErrorSignalListener($recorder));
    $dispatcher->addListener(GuardrailTriggered::class, new GuardrailSignalListener($recorder));

    // in the feedback endpoint
    $recorder->record(new Feedback($payload->getString('run_id'), Signal::Thumbs, $payload->getBoolean('helpful')));

The Langfuse recorder sends feedback as a score on the trace, so a reviewer opening a thumbs down sees the whole run
next to it. To keep requests fast, record through the ``MessengerRecorder`` and handle the messages on a worker.

After a few days, the feedback points at a pattern: customers on the prompt version ``2026-08-v2`` rate refund
answers down, because the agent writes "your refund has been processed" without ever calling ``open_refund``.

Step 6: Turn Failures into Eval Cases
-------------------------------------

Each reviewed failure becomes an eval case, so the fix can be verified and the bug cannot come back. Pull the
negatively rated traces from Langfuse as a starting point::

    use Symfony\AI\Eval\Bridge\Langfuse\LangfuseDatasetSource;
    use Symfony\Component\Yaml\Yaml;

    $source = new LangfuseDatasetSource($httpClient, $langfuseHost, $publicKey, $secretKey);
    $dataset = $source->pull('support-regressions', 'thumbs', 0, new \DateTimeImmutable('-7 days'), labels: ['triage']);

    file_put_contents('evals/datasets/support-regressions.yaml', Yaml::dump($dataset->toArray(), 6, 4));

Then review each case: label the failure, add the expectations, and record the tool results as fixtures, so the
evaluation never opens a real refund. The case keeps the trace ID it was taken from:

.. code-block:: yaml

    # evals/datasets/support-regressions.yaml
    name: support-regressions
    cases:
        - id: refund-without-ticket-001
          source_run: 4bf92f3577b34da6a3ce929d0e0e4736
          labels: [refund]
          input:
              - user: 'I want my money back for SO-10023, the jacket is too small.'
          fixtures:
              order_lookup:
                  SO-10023: { status: delivered }
              open_refund: 'RF-4711'
          expect:
              tools_called: [order_lookup, open_refund]
              tool_order: strict
              not_contains: ['has been processed']
              judge:
                  criteria: 'Only confirms the refund after a ticket id is available, and mentions that ticket id.'

Step 7: Evaluate the Candidate against the Baseline
---------------------------------------------------

An eval suite runs every case for every variant of the agent and scores the runs. Compare the current prompt, the
fixed one, and a cheaper model with the fixed prompt::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Agent\Toolbox\ToolboxInterface;
    use Symfony\AI\Eval\Dataset;
    use Symfony\AI\Eval\EvalCase;
    use Symfony\AI\Eval\Evaluator\LlmJudge;
    use Symfony\AI\Eval\Evaluator\TextAssertions;
    use Symfony\AI\Eval\Evaluator\ToolArguments;
    use Symfony\AI\Eval\Evaluator\ToolsCalled;
    use Symfony\AI\Eval\Gate\PassRateGate;
    use Symfony\AI\Eval\Gate\RegressionGate;
    use Symfony\AI\Eval\Suite;
    use Symfony\AI\Eval\Variant;

    $suite = new Suite(
        'support',
        [Dataset::fromFile('evals/datasets/support-golden.yaml'), Dataset::fromFile('evals/datasets/support-regressions.yaml')],
        [
            new Variant('baseline', ['model' => 'claude-sonnet-4-5', 'prompt' => '2026-08-v2']),
            new Variant('candidate', ['model' => 'claude-sonnet-4-5', 'prompt' => '2026-09-v3']),
            new Variant('cheap', ['model' => 'claude-haiku-4-5', 'prompt' => '2026-09-v3']),
        ],
        static fn (Variant $variant, ?ToolboxInterface $toolbox, EvalCase $case): Agent => new Agent(
            $platform,
            $variant->get('model'),
            instruction: $prompts->get('support/system', $variant->get('prompt'))->toInstruction(['shop_name' => 'ACME']),
            toolbox: $toolbox,
        ),
        [new ToolsCalled(), new ToolArguments(), new TextAssertions(), new LlmJudge($platform, 'claude-opus-4-5')],
        [
            new PassRateGate(0.9, variants: ['candidate', 'cheap']),
            new PassRateGate(0.95, 'refund', ['candidate', 'cheap']),
            new RegressionGate('baseline', 0.02),
        ],
        repetitions: 3,
        toolCatalog: new Toolbox([new ShopTools()]),
    );

The suite hands the agent factory a toolbox that exposes your real tool definitions, but answers the calls with the
fixtures of the case. Deterministic evaluators check the tool trajectory and the answer, the LLM judge checks the
free-form criteria, and the gates decide whether a variant may be released. Register
:class:`Symfony\\AI\\Eval\\Command\\RunCommand` with your suites as a console command and run ``ai:eval:run``:

.. code-block:: terminal

    $ php bin/console ai:eval:run support --report=markdown:var/eval/summary.md --report=junit:var/eval/junit.xml

    Suite "support" - 10 results per variant

     Variant     Pass rate   escalation   refund    status    Avg tokens   Avg cost   Flaky
     ------------------------------------------------------------------------------------------
     baseline    40.0 %      100.0 %      0.0 %     100.0 %   1,452        $0.0057    0
     candidate   100.0 %     100.0 %      100.0 %   100.0 %   2,160        $0.0082    0
     cheap       80.0 %      0.0 %        100.0 %   100.0 %   2,160        $0.0027    0

     Gates
      OK   candidate  pass_rate 100.0 % >= 90 %
      FAIL cheap      pass_rate 80.0 % < 90 %
      OK   candidate  pass_rate[refund] 100.0 % >= 95 %
      OK   cheap      pass_rate[refund] 100.0 % >= 95 %
      OK   candidate  no drop above 2.0 points vs baseline
      FAIL cheap      escalation dropped 100.0 points vs baseline (max 2.0)

This is the output of the example, shortened. The candidate fixes the refunds and keeps everything else, so it can be released. The cheaper model would save
two thirds of the cost, but stops handing over to a human, so the gate keeps it out. The command exits with ``1``
whenever a gate fails, which makes it a release check in CI.

Step 8: Calibrate the Judge
---------------------------

A judge that disagrees with humans makes your scores meaningless. Label a sample of runs yourself with the same
criteria and measure the agreement::

    use Symfony\AI\Eval\Calibration\JudgeCalibrator;
    use Symfony\AI\Eval\Calibration\LabeledRun;

    $calibration = (new JudgeCalibrator())->calibrate($judge, $labeledRuns);

    if ($calibration->getKappa() < 0.7) {
        // improve the judge prompt, starting from $calibration->getDisagreements()
    }

A false pass, where the judge accepts an answer humans reject, is the dangerous kind of disagreement: it lets
regressions through. Re-run the calibration whenever you change the judge model or prompt.

Step 9: Keep the Fast Checks in Every Build
-------------------------------------------

The eval suite calls real models, so run it nightly and on pull requests that change prompts, models or tools. For
every build, assert the agent's behavior in PHPUnit against recorded model responses:

.. code-block:: php

    use Symfony\AI\Eval\RunRecorder;
    use Symfony\AI\Eval\Test\AgentAssertionsTrait;

    final class RefundFlowTest extends KernelTestCase
    {
        use AgentAssertionsTrait;

        public function testRefundIsOnlyConfirmedWithTicket()
        {
            $run = RunRecorder::record(self::getContainer()->get('ai.agent.support'), 'I want my money back for SO-10023.');

            self::assertToolsCalledInOrder(['order_lookup', 'open_refund'], $run);
            self::assertAnswerMatches('/RF-\d+/', $run);
        }
    }

To evaluate production traffic continuously, add the ``SamplingProcessor`` of the Eval component to the agent: it
sends a sample of the runs to a message bus, and the ``EvaluateRunHandler`` records the scores as feedback, right
next to the ratings of your customers.

Going Further
-------------

* :doc:`/components/agent` - run context, versioned prompts, budgets and guardrails
* :doc:`/bridges/open-telemetry` - spans, content capture and exporting to Langfuse
* :doc:`/components/feedback` - feedback recorders and implicit signals
* :doc:`/components/eval` - datasets, evaluators, gates, reports and simulations
