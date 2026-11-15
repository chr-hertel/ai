CHANGELOG
=========

1.0
---

 * Add `Contract\EvaluationNormalizer`, normalizing an `Evaluation` into the request body of TypeSafe, Ollama and OpenRouter, for platforms to register in their contract
 * Add getters to `Evaluation` and to the questions
 * [BC BREAK] `Evaluation` and the questions don't implement `JsonSerializable` anymore, the contract of each platform normalizes them
 * [BC BREAK] Remove `RequestBodyBuilder`, the contract normalizes the evaluation before the model client sends it
 * [BC BREAK] Remove the `fromArray()` methods of `NoulAnswer`, `ChoiceAnswer` and `ScoreAnswer`
 * [BC BREAK] Restrict the instructions of questions to strings
 * [BC BREAK] Require at least two options for `ChoiceQuestion`
 * Add optional `Image` inputs to `Evaluation`, omitted from text-only payloads

0.15
----

 * Add the bridge, holding the `Evaluation`, `Question\*`, `Answer\*`, `AnswersConverter` and `TokenUsageExtractor` classes shared by providers of decision models
