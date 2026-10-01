CHANGELOG
=========

0.15
----

 * Add the bridge
 * Seed the agent's run context with the trace ID, record it as `app.*` span attributes and add the trace ID as `trace_id` to the result metadata
 * Add `ContentRedactorInterface` and `RegexContentRedactor` to mask captured content
 * Add `EventListener\GuardrailSpanListener` recording guardrail interventions as span events
