CHANGELOG
=========

0.14
----

 * Add model information to token usage extraction
 * [BC BREAK] Replace the model clients and result converters with `ChatCompletionsClient`, `ImageClient`, `MusicClient`, `SpeechClient` and `VideoClient`
 * [BC BREAK] Stop polling asynchronous tasks inside `SpeechClient` and `VideoClient`. Video generation and asynchronous speech synthesis now return a `Result\JobResult` carrying a serializable job handle, resolved through the new `MiniMaxJobClient` built by `Factory::createJobClient()` — see the platform `UPGRADE` notes

0.11
----

 * Add the bridge
