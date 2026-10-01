# Closing the Loop

Production traffic, traces, feedback, a dataset of regressions and an eval gate for a support agent, end to end:

```bash
php closing-the-loop/production.php    # traced traffic, feedback, triage into var/support-regressions.yaml
php closing-the-loop/eval.php          # all variants, exits 1 because the cheap model misses escalations
php closing-the-loop/eval.php --variant=baseline --variant=candidate    # the release decision, exits 0
```

The model is scripted (`support.php`), so the loop runs offline and deterministically: with the prompt version
`2026-08-v2` it promises refunds without opening them, `claude-haiku-4-5` does not hand over to a human. The traces go
to an in-memory OpenTelemetry exporter; with `LANGFUSE_HOST`, `LANGFUSE_PUBLIC_KEY` and `LANGFUSE_SECRET_KEY` set, the
feedback is also sent to Langfuse as scores.

| Step | Components |
|---|---|
| Run context, prompt rollout, budget | Agent: `RunContext`, `YamlPromptRegistry`, `PromptInstructionListener`, `RunBudget` |
| Tracing, redaction, guardrail events | OpenTelemetry bridge: `TracingAgent`, `RegexContentRedactor`, `GuardrailSpanListener` |
| Explicit and implicit feedback | Feedback: `Feedback`, `ToolErrorSignalListener`, `GuardrailSignalListener` |
| Dataset, variants, evaluators, gates | Eval: `Dataset`, `Suite`, `FixtureToolbox`, `LlmJudge`, `RegressionGate`, `ai:eval:run` |
| Judge calibration | Eval: `JudgeCalibrator` |
