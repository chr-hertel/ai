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
is set implicitly — `./runner --record` and `./runner --replay` inject it into every
example process, and the PHPUnit replay tests set `CASSETTE=replay` themselves:

- `./runner --record openai`: runs the examples live (API keys required), captures every
  HTTP interaction (credentials redacted) into `fixtures/<path>.json`, then replays each
  fresh cassette and freezes its output as the golden `fixtures/<path>.out`.
- `./runner --replay openai`: runs the examples offline against their cassettes.
- `vendor/bin/phpunit`: replays every example that has a cassette and compares its
  output to the committed golden; this is what CI runs, without keys.

Recording is a local maintainer task — CI has no credentials for the providers, so it
only ever replays. To refresh a single cassette, set the variable by hand:

```bash
CASSETTE=record php openai/chat.php
CASSETTE=replay php openai/chat.php > fixtures/openai/chat.out
```

See `README.md` for the full workflow.