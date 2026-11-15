CHANGELOG
=========

1.0
---

 * [BC BREAK] Replace the model clients and result converters with `ChatCompletionsClient`, `ImageClient`, `MusicClient`, `SpeechClient` and `VideoClient`

0.14
----

 * Add model information to token usage extraction
 * [BC BREAK] Stop polling asynchronous tasks inside `MiniMaxResultConverter`. Video generation and asynchronous speech synthesis now return a `Result\JobResult` carrying a serializable job handle, resolved through the new `MiniMaxJobClient`, built by `Factory::createJobClient()` — see the platform `UPGRADE` notes. `MiniMaxResultConverter` no longer takes an HTTP client, API key, endpoint or clock, only an optional provider name, which it stamps onto the handles it creates

0.11
----

 * Add the bridge
