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

To start a dataset from production or to keep it in Langfuse, see `Langfuse Datasets`_.

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

Evaluators
----------

An evaluator only scores the cases that have its expectation. It skips all other cases. The component comes with
these evaluators:

``ToolsCalled``
    Checks the tools in ``expect.tools_called``, in the given order if ``expect.tool_order`` is ``strict``.
``ToolArguments``
    Checks the arguments in ``expect.tool_arguments``, either as exact value or as regular expression.
``TextAssertions``
    Checks the final answer against ``expect.contains``, ``expect.not_contains`` and ``expect.matches``.
``Limits``
    Checks ``expect.max_tool_calls``, ``expect.max_tokens`` and ``expect.max_latency`` (in seconds).
``JsonSchema``
    Checks that the final answer is valid JSON and matches the JSON schema in ``expect.json_schema``.
``LlmJudge``
    Lets a model grade the run against the criteria in ``expect.judge.criteria``.
``Faithfulness``
    Lets a model check that the final answer is supported by the tool results of the run.
``Similarity``
    Compares the final answer with the reference answer in ``expect.answer`` by meaning.

The ``JsonSchema`` evaluator needs the ``opis/json-schema`` package:

.. code-block:: terminal

    $ composer require --dev opis/json-schema

If the agent uses structured output, the evaluator validates the object. A text answer must be plain JSON, without
Markdown code fences around it:

.. code-block:: yaml

    expect:
        json_schema:
            type: object
            properties:
                ticket: { type: string, pattern: '^RF-\d+$' }
            required: [ticket]

The ``Faithfulness`` evaluator catches answers that make up facts. It does not need an expectation: the model lists
the claims of the final answer and checks each one against the results of the tool calls. The score is the share of
supported claims, and the run passes from ``0.8`` on. Runs without an answer or without a successful tool call have
nothing to check against, so the evaluator skips them::

    use Symfony\AI\Eval\Evaluator\Faithfulness;

    $faithfulness = new Faithfulness($platform, 'claude-opus-4-5', threshold: 0.9);

The ``Similarity`` evaluator is useful when many answers are correct, but you cannot list the words they must
contain. It creates embeddings of the final answer and of ``expect.answer`` and passes if their cosine similarity is
at least ``0.8``::

    use Symfony\AI\Eval\Evaluator\Similarity;

    $similarity = new Similarity($platform, 'text-embedding-3-small', threshold: 0.85);

The threshold depends on the embeddings model, so check the scores of a few good and bad answers before you choose
it.

Langfuse Datasets
-----------------

`Langfuse`_ stores the traces of your agent in production and can keep datasets for you. The Langfuse dataset source
turns both into eval cases. Install it first:

.. code-block:: terminal

    $ composer require --dev symfony/ai-langfuse-dataset-source

To review the failures of the last days, pull the traces with a given score. Each trace becomes a case with the trace
input and the trace ID as ``source_run``. You add the expectations::

    use Symfony\AI\Eval\Bridge\Langfuse\LangfuseDatasetSource;

    $source = new LangfuseDatasetSource($httpClient, 'https://cloud.langfuse.com', $publicKey, $secretKey);

    // traces rated with a thumbs down in the last 7 days, at most 50 by default
    $dataset = $source->pull('support-regressions', 'thumbs', false, new \DateTimeImmutable('-7 days'), labels: ['triage']);

A `Langfuse dataset`_ keeps the reviewed cases in Langfuse, so your team can curate them in the Langfuse UI. The
``fetch()`` method reads its active items::

    $dataset = $source->fetch('support-regressions');

Each item becomes a case with the item ID as case ID:

* The item input becomes the case input. A user message or a list of ``role`` and ``content`` messages is read as
  conversation, other input is passed to the agent as JSON;
* An expected output object becomes the expectations of the case, any other expected output is stored as the
  ``expected_output`` expectation;
* The ``labels`` and ``fixtures`` keys of the item metadata become the labels and fixtures of the case. The other
  keys are stored in the ``metadata`` of the case, together with the ``langfuse_item_id`` and ``langfuse_dataset_id``.

The ``ai:eval:dataset:pull`` command writes a Langfuse dataset to a dataset file, which you can load with
``Dataset::fromFile()``:

.. code-block:: terminal

    # writes evals/datasets/support-regressions.yaml, use --force to overwrite an existing file
    $ php bin/console ai:eval:dataset:pull support-regressions

    $ php bin/console ai:eval:dataset:pull support-regressions tests/evals/support.yaml

The ``ai:eval:dataset:add`` command adds an item to a Langfuse dataset. With ``--source-trace-id``, a trace from
production becomes a test case. If you don't pass an ``--input``, the item takes the input of the trace:

.. code-block:: terminal

    $ php bin/console ai:eval:dataset:add support-regressions \
        --source-trace-id=4bf92f3577b34da6a3ce929d0e0e4736 \
        --expect='{"tools_called": ["order_lookup", "open_refund"]}' \
        --label=refund

    # a user message with a reference answer, an item with the same --id is updated
    $ php bin/console ai:eval:dataset:add support-faq --input="Do you ship to Norway?" \
        --expect="Yes, within 5 days." --id=shipping-norway

The ``--input`` option takes a user message or a conversation as JSON list, e.g.
``[{"user": "Where is SO-10023?"}, {"assistant": "It was delivered."}, {"user": "It was not."}]``. The ``--metadata``
option takes a JSON object.

The :class:`Symfony\\AI\\Eval\\Bridge\\Langfuse\\Command\\DatasetPullCommand` and
:class:`Symfony\\AI\\Eval\\Bridge\\Langfuse\\Command\\DatasetAddCommand` need the ``symfony/console`` package, and
the pull command needs ``symfony/yaml``.

Pushing Runs to Langfuse
~~~~~~~~~~~~~~~~~~~~~~~~

To compare runs in the Langfuse UI, push the results of ``ai:eval:run`` as `Langfuse experiments`_:

.. code-block:: terminal

    $ php bin/console ai:eval:run support --push=langfuse

The ``--push`` option takes the name of a :class:`Symfony\\AI\\Eval\\Report\\ReportPublisherInterface` that you pass
to the :class:`Symfony\\AI\\Eval\\Command\\RunCommand`. The
:class:`Symfony\\AI\\Eval\\Bridge\\Langfuse\\LangfuseReportPublisher` creates one experiment per variant, and one per
repetition if the suite repeats its cases::

    use Symfony\AI\Eval\Bridge\Langfuse\LangfuseReportPublisher;
    use Symfony\AI\Eval\Command\RunCommand;

    $publisher = new LangfuseReportPublisher($httpClient, 'https://cloud.langfuse.com', $publicKey, $secretKey);
    $command = new RunCommand($suites, publishers: ['langfuse' => $publisher]);

Each case result becomes an experiment item with the case input, the answer and the expectations. The scores of the
evaluators and a ``passed`` score are attached to the item. If your agent is traced, the ``trace_id`` metadata of the
item points to the trace of the agent.

A case fetched with ``fetch()`` or ``ai:eval:dataset:pull`` is linked to its Langfuse dataset item. The experiment
belongs to the dataset if all its cases come from the same Langfuse dataset. Other cases are pushed as local items
with the case ID.

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
.. _`Langfuse`: https://langfuse.com/
.. _`Langfuse dataset`: https://langfuse.com/docs/evaluation/experiments/datasets
.. _`Langfuse experiments`: https://langfuse.com/docs/evaluation/experiments/data-model
