# Symfony AI Examples

This directory contains various examples of how to use the Symfony AI components. They are meant to provide a
reference implementation to help you get started.

On top, the examples are used as integration tests to ensure that the components work as expected.

## Layout

```
platform/<vendor>/     one directory per platform bridge - openai/, anthropic/, gemini/, ...
component/<component>/ everything that is about a component rather than a provider
```

`platform/` answers "how do I do X with this provider": each directory covers one bridge's API
surface, and the same capability carries the same filename in all of them, so `platform/*/stream.php`
is every provider's streaming example.

`component/` answers "how do I use this part of the library": `agent/`, `platform/`, `chat/`,
`store/` (one file per store backend), `indexer/`, `retriever/`, `document/`, `memory/`,
`multi-agent/`, `toolbox/`, `speech/`, `commands/`, and `rag/` for recipes that compose several of
them into a working retrieval pipeline.

`INDEX.md` lists which capability each platform bridge has an example for - the one view the tree
cannot give you. It is generated, so regenerate it after adding or renaming an example:

```bash
./build-index
```

Two conventions are worth knowing before adding a file:

* a directory may carry its own `bootstrap.php` next to its examples for helpers only that directory
  needs - `platform/perplexity/`, `platform/vertexai/` and `component/store/` do - and it requires
  the shared `examples/bootstrap.php` in turn;
* a file whose name starts with an underscore, like `platform/bedrock/_model.php`, is a helper rather
  than an example, and the runner skips it.

## Running the examples

For setting up and running the examples, you can either run them standalone or via the example runner. You find the
commands for that in this section. Make sure to change into the `examples` directory before running the commands.

```bash
cd examples
```

### Setup

#### Dependencies

Before running the examples, you need to install the dependencies. You can do this by running:

```bash
composer install
```

If you want to run the examples together with local changes, for example while developing a feature, you need to link
the AI components into the vendor directory after `composer install`. You can use the `link` script in the root
directory for this:

```bash
../link
```

#### Configuration

Depending on the examples you want to run, you may need to configure the needed API keys. Therefore, you need to create a
`.env.local` file in the root of the examples' directory. This file should contain the environment variables for the
corresponding example you want to run.

Only secrets are configurable that way: API keys, and endpoints or identifiers bound to a personal account. Everything
else is inlined in the example itself - the hosts and credentials of the services in `compose.yaml`, the defaults of
local daemons like Ollama or LM Studio, and the parameters an example picks for itself. So an example that talks to a
`compose.yaml` service needs nothing but `docker compose up -d`, and `.env` lists exactly what you have to supply.

_Now you can run examples standalone or via the example runner._

#### Store with Docker

Some of the store examples require locally running services, meaning that you need to have Docker installed and running
to test these examples.

```bash
docker compose up -d
```

### Running examples standalone

Every example script is a standalone PHP script that can be run from the command line.
You can run an example by executing the following command:

```bash
php platform/openai/chat.php
```

To get more insights into what is happening at runtime, e.g. HTTP and tool calls, you can add `-vv` or `-vvv`:

```bash
php platform/openai/toolcall-stream.php -vvv
```

### Running examples via the example runner

You can also run the examples via the example runner, which takes care of running the examples parallel in
sub-processes. This is useful if you are contributing to the Symfony AI components and want to ensure that all examples
work as expected.

You can run the example runner by executing the following command:

```bash
./runner
```

If you only want to run examples of one or multiple specific subdirectories, you can pass the name as an
argument - a bare vendor or component name is resolved under `platform/` and `component/` for you:

```bash
./runner openai mistral
./runner platform/openai component/store
```

If you only want to run a specific subset of examples, you can use a filter option:

```bash
./runner --filter=toolcall
```

## Record & replay

Examples don't only run against the live provider APIs — every HTTP interaction can be recorded into a *cassette*
(a JSON file under `tests/fixtures/`, mirroring the example's path) and replayed later without any API keys. Replaying drives
the recorded bytes through the real bridge pipeline (`Platform → ModelClient → ResultConverter`), which turns the
example corpus into deterministic, credential-free integration tests: CI replays every recorded example via PHPUnit
and compares its output against a committed golden (`tests/fixtures/<path>.out`), so a result-converter regression fails
the build.

The switch is the `CASSETTE` environment variable (`record` or `replay`), but you usually don't set it yourself —
the runner and the test suite handle it:

```bash
# Record: run the examples live (API keys required), write cassettes with credentials
# redacted, then verify each recording replays and refresh its golden.
./runner --record openai

# Replay: re-run every recorded example offline and compare against its golden.
# No keys needed - this is also what CI runs.
vendor/bin/phpunit
```

Recording is a local maintainer task, since CI has no provider credentials — a good habit is recording alongside the
live example run before tagging a release: it costs no extra API calls, and the cassette diff shows exactly what the
providers changed since the last recording. Commit cassettes and goldens together.

Binary responses (generated images, audio, ...) are not stored byte-for-byte: the cassette keeps a metadata stub
(content type, byte size) and replay serves a small placeholder body instead.

To record a single example (or a subset), narrow the record run with a filter — the golden
refresh is included:

```bash
./runner --record --filter=chat openai
```
