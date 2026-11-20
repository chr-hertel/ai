# AGENTS.md

AI agent guidance for the Feedback component.

## Component Overview

Records feedback for agent runs: explicit signals from users (thumbs, ratings, corrections), implicit signals derived
from the application (tool errors, guardrail interventions) and automated scores from evaluators. Feedback is keyed
by the run ID of the agent's `RunContext`, which is the trace ID when tracing is enabled.

## Architecture

- **Feedback** (`src/Feedback.php`): Immutable value object, with `Signal` and `Source` enums
- **FeedbackRecorderInterface**: Contract of every backend; `ChainRecorder`, `InMemoryRecorder`
- **Messenger** (`src/Messenger/`): Asynchronous recording through Symfony Messenger
- **EventListener** (`src/EventListener/`): Implicit signals from agent events
- **Bridge** (`src/Bridge/`): Backends, e.g. Langfuse scores

## Essential Commands

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
cd ../.. && vendor/bin/php-cs-fixer fix src/feedback/
```
