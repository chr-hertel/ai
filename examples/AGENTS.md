# AGENTS.md

AI agent guidance for Symfony AI examples directory.

## Project Overview

Standalone examples demonstrating Symfony AI component usage across different platforms. Serves as reference implementations and integration tests.

## Essential Commands

### Setup
```bash
composer install
../link  # Link local AI components
docker compose up -d  # For store examples
```

### Running Examples
```bash
# Single example
php openai/chat.php
php openai/toolcall-stream.php -vvv

# Batch execution
./runner  # All examples
./runner openai mistral  # Specific platforms
./runner --filter=toolcall  # Pattern filter
```

### Environment
Configure API keys in `.env.local` (copy from `.env` template).

`.env` holds secrets only - API keys, and endpoints or identifiers bound to a personal account. A value that the local
Docker setup pins, a local daemon's documented default, or a parameter the example chooses for itself is inlined in the
example instead of being read through `env()`. When adding an example, ask whether the reader must supply the value: if
not, write it into the code.

## Naming

An example file is named `<capability>[-<variant>].php`, where the capability comes from a fixed
vocabulary so that the same thing is called the same thing everywhere:

`chat`, `stream`, `embeddings`, `toolcall`, `structured-output`, `token-metadata`, `speech-to-text`,
`text-to-speech`, `audio-input`, `image-input`, `image-generation`, `image-editing`, `pdf-input`,
`server-tools`, `rerank`.

Anything not on that list needs a good reason: `vision`, for instance, is not a capability here -
an example that describes a picture is `image-input`.

Variants qualify the capability rather than replacing it: `-binary` / `-url` for how content is
passed, `-stream`, `-parallel`, `-roundtrip`, `-multiple`, or the name of the technique being shown.
A provider-side tool is `server-tools-<tool>`, whichever provider offers it.

Never name a file after a model: model names rot on deprecation, and a reader looking for "how do I
stream" should not have to know which model the example happens to use. A second example for the
same capability is justified only when it exercises a genuinely different code path - Bedrock's
Claude and Nova examples do, because they go through different model clients, and their suffixes say
which one rather than which model is fashionable.

The exception is a provider whose own API names a capability differently: the HuggingFace examples
follow HuggingFace's task names (`fill-mask`, `token-classification`, ...) and Scaleway's
`responses*` examples follow its Responses API, because that is what a reader searching those docs
will look for.

## Architecture

### Directory Structure
- Platform directories: `openai/`, `anthropic/`, `gemini/`, etc.
- `misc/`: Cross-platform examples
- `rag/`: Retrieval Augmented Generation examples
- `toolbox/`: Utility tools and integrations
- `bootstrap.php`: Common setup for all examples

### Patterns
- Shared `bootstrap.php` setup
- Consistent structure across platforms
- Verbose output flags (`-vv`, `-vvv`)
- Synchronous and streaming demos

### Dependencies
Uses `@dev` versions:
- `symfony/ai-platform`
- `symfony/ai-agent`
- `symfony/ai-store`

## Development Notes

- Examples serve as integration tests
- Runner executes in parallel for platform verification
- Demonstrates both sync and async patterns
- Platform-specific client configurations

## Record & replay (offline integration tests)

`bootstrap.php`'s `http_client()` is record/replay aware via the `CASSETTE` environment
variable (`record` or `replay`, unset = real APIs), turning the example corpus into
deterministic, credential-free integration tests for the bridge pipeline. The variable
is set implicitly — `./runner --record` injects it into every example process, and the
PHPUnit replay tests set `CASSETTE=replay` themselves:

- `./runner --record openai`: runs the examples live (API keys required), captures every
  HTTP interaction (credentials redacted, binary bodies elided to metadata stubs) into
  `tests/fixtures/<path>.json`, then replays each fresh cassette and freezes its output
  as the golden `tests/fixtures/<path>.out`.
- `vendor/bin/phpunit`: replays every example that has a cassette and compares its
  output to the committed golden; this is what CI runs, without keys.

Recording is a local maintainer task — CI has no credentials for the providers, so it
only ever replays. To refresh a single cassette (golden refresh included), narrow the
record run with a filter:

```bash
./runner --record --filter=chat openai
```

See `README.md` for the full workflow.