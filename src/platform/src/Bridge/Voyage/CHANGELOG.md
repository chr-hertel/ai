CHANGELOG
=========

0.14
----

 * [BC BREAK] Replace the model clients and result converters with `EmbeddingsClient` and `MultimodalEmbeddingsClient`

0.11
----

 * Add a `baseUrl` argument to the model clients and the factory to target Voyage-compatible endpoints

0.8
---

 * [BC BREAK] `VoyageContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.1
---

 * Add the bridge
