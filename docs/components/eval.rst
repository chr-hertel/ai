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

Suites, Variants and Gates
--------------------------

A :class:`Symfony\\AI\\Eval\\Suite` combines datasets, the variants of the agent to compare, evaluators and gates::

    use Symfony\AI\Eval\Dataset;
    use Symfony\AI\Eval\EvalRunner;
    use Symfony\AI\Eval\Evaluator;
    use Symfony\AI\Eval\Gate;
    use Symfony\AI\Eval\Suite;
    use Symfony\AI\Eval\Variant;

    $suite = new Suite(
        'support',
        [Dataset::fromFile('evals/datasets/support-regressions.yaml')],
        [new Variant('baseline', ['prompt' => '2026-08-v2']), new Variant('candidate', ['prompt' => '2026-09-v3'])],
        static fn (Variant $variant, ?ToolboxInterface $toolbox): Agent => new Agent($platform, 'claude-sonnet-4-5',
            instruction: $prompts->get('support/system', $variant->get('prompt'))->toInstruction(),
            toolbox: $toolbox,
        ),
        [new Evaluator\ToolsCalled(), new Evaluator\TextAssertions(), new Evaluator\LlmJudge($platform, 'claude-opus-4-5')],
        [new Gate\PassRateGate(0.9, variants: ['candidate']), new Gate\RegressionGate('baseline', 0.02)],
        repetitions: 3,
        toolCatalog: $toolbox,
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

    self::assertToolsCalledInOrder(['order_lookup', 'open_refund'], $run);
    self::assertAnswerMatches('/RF-\d+/', $run);

Calibrating Judges and Online Evaluation
----------------------------------------

The :class:`Symfony\\AI\\Eval\\Calibration\\JudgeCalibrator` measures the agreement of an LLM judge with human labels,
including Cohen's kappa, so a drifting judge is noticed before its scores mislead anyone. The
:class:`Symfony\\AI\\Eval\\Online\\SamplingProcessor` sends a sample of production runs to a message bus, where the
:class:`Symfony\\AI\\Eval\\Online\\EvaluateRunHandler` records the scores as feedback next to the feedback of users.
