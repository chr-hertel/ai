Decision Platform
=================

Decision platform bridge for Symfony AI.

It holds the classes that platforms with decision models share: the `Evaluation` input, the
question and answer types, and the contract normalizer and answers converter of the request format
that TypeSafe, Ollama and OpenRouter use. Gemini and OpenAI add their own normalizer to their contract.
It does not talk to any provider on its own.

Decision Documentation
----------------------

 * [Decision Models](https://symfony.com/doc/current/ai/components/platform/decision.html)
 * [TypeSafe System One](https://docs.typesafe.ai/concepts/system-one)
 * [TypeSafe Questions](https://docs.typesafe.ai/primitives)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
