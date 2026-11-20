CHANGELOG
=========

1.0
---

 * Add the component
 * Add the `ai:eval:calibrate` command to check the agreement of a judge with human labels
 * Add the `MinKappaGate` to fail a suite if a judge agrees too little with human labels
 * Add `TargetController` to expose an agent over HTTP to external eval tools like promptfoo and garak
 * Add the `JsonSchema`, `Faithfulness` and `Similarity` evaluators
 * Add metadata to `EvalCase`, read from and written to the `metadata` of a dataset case
 * Add `ReportPublisherInterface` and the `--push` option of the `ai:eval:run` command
