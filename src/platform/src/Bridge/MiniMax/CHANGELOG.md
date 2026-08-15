CHANGELOG
=========

0.14
----

 * Add model information to token usage extraction
 * [BC BREAK] Replace the model clients and result converters with `ChatCompletionsClient`, `ImageClient`, `MusicClient`, `SpeechClient` and `VideoClient`

0.13
----

 * Fail with the provider's own message when MiniMax rejects a request. Such a rejection arrives as HTTP 200 with the reason in `base_resp` (e.g. `2054 "voice id not exist"`, `1008 "insufficient balance"`) and no payload, which previously surfaced as an error about a missing array key — or, for asynchronous tasks, as polling a task that never existed until the budget ran out
 * [BC BREAK] Stop polling asynchronous tasks inside `SpeechClient` and `VideoClient`. Video generation and asynchronous speech synthesis now return a `Result\JobResult` carrying a serializable job handle, resolved through the new `MiniMaxJobClient` — see the platform `UPGRADE` notes. The MiniMax clients no longer take a clock

0.11
----

 * Add the bridge
