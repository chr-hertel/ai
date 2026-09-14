CHANGELOG
=========

0.14
----

 * [BC BREAK] Replace the model clients and result converters with `GenerateClient` and `EditClient`

0.11
----

 * Normalize the base URL and tolerate a trailing slash
 * [BC BREAK] Rename the `$hostUrl` constructor and factory argument to `$baseUrl`

0.8
---

 * [BC BREAK] `DecartContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.1
---

 * Add the bridge
