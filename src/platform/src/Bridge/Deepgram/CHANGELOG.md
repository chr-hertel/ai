CHANGELOG
=========

1.0
---

 * [BC BREAK] Replace the model clients and result converters with `ListenClient` and `SpeakClient`
 * Add a `$modelCatalog` argument to `Factory::createProvider()` and `Factory::createPlatform()`, and `Factory::createModelCatalog()` to build the API-based catalog on its own, e.g. to wrap it in a `CachedModelCatalog`

0.11
----

 * Add the bridge with HTTP transport for both speech-to-text (`/v1/listen`) and text-to-speech (`/v1/speak`)
