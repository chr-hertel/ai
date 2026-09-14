CHANGELOG
=========

0.14
----

 * [BC BREAK] Replace the model clients and result converters with `SpeechToTextClient` and `TextToSpeechClient`

0.11
----

 * Add a `baseUrl` argument to the model clients and the factory to target Cartesia-compatible endpoints

0.8
---

 * [BC BREAK] `CartesiaContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.1
---

 * Add the bridge
