Symfony AI - Eval Component
===========================

The Eval component evaluates agents against datasets of cases: it runs every case for every variant of the agent,
scores the runs with deterministic evaluators and LLM judges, checks gates for CI and writes reports. The same
recorded runs are used for offline evaluation, for online evaluation of production traffic and in PHPUnit tests.

Installation
------------

.. code-block:: terminal

    $ composer require --dev symfony/ai-eval

Datasets
--------

A dataset lives in your repository next to the code it tests. Each case has an input, labels to group results, tool
fixtures that keep the evaluation hermetic, and the expectations the evaluators check:

.. code-block:: yaml

    # evals/datasets/support-regressions.yaml
    name: support-regressions
    cases:
        - id: refund-without-ticket-001
          source_run: 4bf92f3577b34da6a3ce929d0e0e4736
          labels: [refund]
          input:
              - user: "I want my money back for SO-10023, the jacket is too small."
          fixtures:
              order_lookup:
                  SO-10023: { status: delivered }
              open_refund: 'RF-4711'
          expect:
              tools_called: [order_lookup, open_refund]
              tool_order: strict
              not_contains: ['has been processed']
              judge:
                  criteria: "Only confirms the refund after a ticket id is available."

The :class:`Symfony\\AI\\Eval\\Toolbox\\FixtureToolbox` exposes the real tool definitions to the model, but answers
tool calls with the fixtures of the case.

To start a dataset from production, pull the traces with a given score from Langfuse. Install the Langfuse dataset
source first:

.. code-block:: terminal

    $ composer require --dev symfony/ai-langfuse-dataset-source

Each trace becomes a case with the trace input and the trace ID as ``source_run``. You add the expectations::

    use Symfony\AI\Eval\Bridge\Langfuse\LangfuseDatasetSource;

    $source = new LangfuseDatasetSource($httpClient, 'https://cloud.langfuse.com', $publicKey, $secretKey);

    // traces rated with a thumbs down in the last 7 days, at most 50 by default
    $dataset = $source->pull('support-regressions', 'thumbs', false, new \DateTimeImmutable('-7 days'), labels: ['triage']);

Suites, Variants and Gates
--------------------------

A :class:`Symfony\\AI\\Eval\\Suite` combines datasets, the variants of the agent to compare, evaluators and gates::

    use Symfony\AI\Agent\Prompt\PromptInputProcessor;
    use Symfony\AI\Agent\Prompt\YamlPromptRegistry;
    use Symfony\AI\Eval\Dataset;
    use Symfony\AI\Eval\EvalRunner;
    use Symfony\AI\Eval\Evaluator;
    use Symfony\AI\Eval\Gate;
    use Symfony\AI\Eval\Suite;
    use Symfony\AI\Eval\Variant;

    $prompts = new YamlPromptRegistry(['config/prompts']);

    $suite = new Suite(
        'support',
        [Dataset::fromFile('evals/datasets/support-regressions.yaml')],
        [new Variant('baseline', ['prompt' => '2026-08-v2']), new Variant('candidate', ['prompt' => '2026-09-v3'])],
        static fn (Variant $variant, ?ToolboxInterface $toolbox): Agent => new Agent($platform, 'claude-sonnet-4-5',
            [new PromptInputProcessor($prompts, 'support/system', $variant->get('prompt'))],
            toolbox: $toolbox,
        ),
        [new Evaluator\ToolsCalled(), new Evaluator\TextAssertions(), new Evaluator\LlmJudge($platform, 'claude-opus-4-5')],
        [new Gate\PassRateGate(0.9, variants: ['candidate']), new Gate\RegressionGate('baseline', 0.02)],
        repetitions: 3,
        // the real tools, answered by the fixtures of each case
        toolbox: $toolbox,
    );

    $report = (new EvalRunner())->run($suite);

The ``ai:eval:run`` command of :class:`Symfony\\AI\\Eval\\Command\\RunCommand` runs a suite, writes reports in the
``text``, ``markdown``, ``json`` and ``junit`` format and exits with ``1`` if a gate fails.

Testing Agent Behavior
----------------------

The :class:`Symfony\\AI\\Eval\\Test\\AgentAssertionsTrait` brings the deterministic evaluators to PHPUnit::

    use Symfony\AI\Eval\RunRecorder;
    use Symfony\AI\Eval\Test\AgentAssertionsTrait;

    $run = RunRecorder::record($agent, 'I want my money back for SO-10023.');

    $this->assertToolsCalledInOrder(['order_lookup', 'open_refund'], $run);
    $this->assertAnswerMatches('/RF-\d+/', $run);

Calibrating Judges
------------------

An LLM judge is only useful if it agrees with the people who know what a good answer is. The
:class:`Symfony\\AI\\Eval\\Calibration\\JudgeCalibrator` runs a judge on runs a human labeled with the same criteria
and compares the verdicts. The :class:`Symfony\\AI\\Eval\\Calibration\\CalibrationReport` gives you the agreement,
Cohen's kappa, the false pass rate, the false fail rate and the cases where the judge and the human disagree::

    use Symfony\AI\Eval\Calibration\JudgeCalibrator;
    use Symfony\AI\Eval\Calibration\LabeledRun;
    use Symfony\AI\Eval\RunRecorder;

    $labeledRuns = [
        new LabeledRun($case, RunRecorder::record($agent, $case->getMessages()), humanPassed: true),
        // ...
    ];

    $report = (new JudgeCalibrator())->calibrate($judge, $labeledRuns);

    if ($report->getKappa() < 0.6) {
        // the judge is not reliable enough, improve its criteria or use a stronger model
    }

A kappa of ``1.0`` means the judge always agrees with the human, ``0.0`` means it agrees only as often as chance.
Values above ``0.6`` are usually considered substantial agreement.

The ``ai:eval:calibrate`` command of :class:`Symfony\\AI\\Eval\\Command\\CalibrateCommand` does the same on the
command line. It takes the judges and the sets of labeled runs, keyed by the name of the set::

    use Symfony\AI\Eval\Command\CalibrateCommand;

    $command = new CalibrateCommand([$judge], ['support-labeled' => $labeledRuns]);

Pass the name of the judge, as returned by its ``getName()`` method, and the name of the set. With the ``--min-kappa`` option, the command exits with ``1`` if the
kappa is below the given value, so you can check the judge in CI:

.. code-block:: terminal

    $ php bin/console ai:eval:calibrate llm_judge support-labeled --min-kappa=0.6

Run the calibration when you change the judge, its criteria or its model, not on every run of a suite. The result
does not depend on the agent under test, and every calibration pays for a judge call per labeled run.

Online Evaluation
-----------------

The :class:`Symfony\\AI\\Eval\\Online\\SamplingProcessor` is an output processor of the agent. It sends a sample of
production runs to a message bus, where the
:class:`Symfony\\AI\\Eval\\Online\\EvaluateRunHandler` records the scores as feedback next to the feedback of users.

Testing with External Tools
---------------------------

Tools like `promptfoo`_ and `garak`_ test an agent from the outside, for example with red-teaming attacks. They call
the agent over HTTP. The :class:`Symfony\\AI\\Eval\\Http\\TargetController` turns a request of these tools into a call
of your agent. Install the HttpFoundation component first:

.. code-block:: terminal

    $ composer require --dev symfony/http-foundation

.. warning::

    The controller calls your agent with any prompt it receives, without authentication. Only use it in your
    development environment and never expose it in production.

The controller takes a JSON body with a ``prompt``:

.. code-block:: json

    {"prompt": "I want my money back for SO-10023."}

Or with a list of ``messages`` with the roles ``system``, ``user`` and ``assistant``:

.. code-block:: json

    {
        "messages": [
            {"role": "user", "content": "I want my money back."},
            {"role": "assistant", "content": "For which order?"},
            {"role": "user", "content": "SO-10023"}
        ]
    }

It answers with the text of the agent in ``output``, the run ID, the token usage, the tool calls and the duration in
seconds:

.. code-block:: json

    {
        "output": "I opened the refund ticket RF-4711 for you.",
        "run_id": "01a1f6a8-8ef8-7e59-9838-dd86e708e94e",
        "token_usage": {"prompt": 120, "completion": 18, "total": 138},
        "tool_calls": [{"name": "order_lookup", "arguments": {"order": "SO-10023"}}],
        "duration": 1.84
    }

An invalid request gets a ``400`` response with an ``error`` message. The controller works with any framework that
uses HttpFoundation. In a Symfony application, register it only in the ``dev`` environment:

.. code-block:: yaml

    # config/services.yaml
    when@dev:
        services:
            ai.eval.target:
                class: Symfony\AI\Eval\Http\TargetController
                arguments: ['@ai.agent.support']
                tags: ['controller.service_arguments']

    # config/routes.yaml
    when@dev:
        eval_target:
            path: /_eval/target
            controller: ai.eval.target
            methods: [POST]

Then point promptfoo to the route with its HTTP provider:

.. code-block:: yaml

    # promptfooconfig.yaml
    providers:
        - id: http
          config:
              url: http://localhost:8000/_eval/target
              method: POST
              headers:
                  Content-Type: application/json
              body:
                  prompt: '{{prompt}}'
              transformResponse: '({ output: json.output, tokenUsage: json.token_usage })'

For garak, use the REST generator with a generator options file and run ``garak --model_type rest -G rest.json``:

.. code-block:: json

    {
        "rest": {
            "RestGenerator": {
                "name": "support agent",
                "uri": "http://localhost:8000/_eval/target",
                "method": "post",
                "headers": {"Content-Type": "application/json"},
                "req_template_json_object": {"prompt": "$INPUT"},
                "response_json": true,
                "response_json_field": "output"
            }
        }
    }

.. _`promptfoo`: https://www.promptfoo.dev/docs/providers/http/
.. _`garak`: https://reference.garak.ai/en/latest/garak.generators.rest.html
