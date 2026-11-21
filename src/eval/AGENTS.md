# AGENTS.md

AI agent guidance for the Eval component.

## Component Overview

Evaluates agents offline (datasets, variants, gates), online (sampled production runs) and in tests (PHPUnit
assertions). Everything works on a `RecordedRun`, built from the turns an agent execution reports.

## Architecture

- **EvalCase / Dataset**: Cases with input, labels, tool fixtures and expectations, loaded from YAML
- **RunRecorder / RecordedRun**: Runs an agent and records turns, tool calls, usage and duration
- **Evaluator** (`src/Evaluator/`): Deterministic evaluators and the model-graded `LlmJudge`
- **Toolbox\FixtureToolbox**: Answers tool calls with the fixtures of a case
- **Suite / Variant / EvalRunner**: The matrix of agent configurations, run against every case
- **Report / Gate** (`src/Report/`, `src/Gate/`): Metrics, CI gates and formatters
- **Calibration**: Agreement of a judge with human labels
- **Online**: Sampled evaluation of production runs via Messenger, recorded as feedback
- **Simulation**: Multi-turn conversations with a simulated user
- **Test\AgentAssertionsTrait**: PHPUnit assertions on agent behavior

## Essential Commands

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
cd ../.. && vendor/bin/php-cs-fixer fix src/eval/
```
