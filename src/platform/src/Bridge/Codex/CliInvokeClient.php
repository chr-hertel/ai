<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\Codex\Exception\CliNotFoundException;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\CustomToolCallResult;
use Symfony\AI\Platform\Result\McpCallResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Spawns the Codex CLI as a subprocess and returns the result.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class CliInvokeClient implements ApiClientInterface
{
    /**
     * @param array<string, string> $environment
     */
    public function __construct(
        private readonly ?string $cliBinary = null,
        private readonly ?string $workingDirectory = null,
        private readonly ?float $timeout = 300,
        private readonly array $environment = [],
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Codex;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        // the CLI only runs its built-in tools, it cannot call tools of the application
        if (\is_array($options['tools'] ?? null) && [] !== array_filter($options['tools'], static fn (mixed $tool): bool => !\is_string($tool))) {
            throw MissingModelSupportException::forToolCalling($model);
        }

        if (!isset($options['model'])) {
            $options['model'] = $model->getName();
        }

        $prompt = $this->extractPrompt($payload);

        // Merge payload fields (e.g. system_prompt from the normalizer) into
        // options, giving explicit options priority.
        if (\is_array($payload)) {
            $options = array_merge($payload, $options);
            unset($options['prompt']);
        }

        // Codex CLI has no --system-prompt flag, prepend to prompt instead.
        if (isset($options['system_prompt']) && '' !== $options['system_prompt']) {
            $prompt = \sprintf("[System]\n%s\n\n[User]\n%s", $options['system_prompt'], $prompt);
        }

        $cwd = $options['cwd'] ?? $this->workingDirectory;
        unset($options['cwd'], $options['stream'], $options['system_prompt']);

        if (!isset($options['sandbox'])) {
            $options['sandbox'] = 'read-only';
        }

        $command = $this->buildCommand($prompt, $options);

        $this->logger->info('Spawning Codex CLI subprocess.', [
            'command' => implode(' ', array_map('escapeshellarg', $command)),
            'cwd' => $cwd,
        ]);

        $process = new Process($command, $cwd, $this->environment, null, $this->timeout);
        $process->start();

        return new RawProcessResult($process);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return string[]
     */
    public function buildCommand(string $prompt, array $options = []): array
    {
        if (isset($options['ask_for_approval'])) {
            @trigger_error('The "ask_for_approval" option is ignored by "codex exec" and has no effect; remove it or use "dangerously_bypass_approvals_and_sandbox" to bypass approvals.', \E_USER_DEPRECATED);
            unset($options['ask_for_approval']);
        }

        if ([] !== ($options['tools'] ?? []) || [] !== ($options['allowed_tools'] ?? [])) {
            throw new InvalidArgumentException('The "tools" and "allowed_tools" options are not supported, "codex exec" has no option to restrict its tools.');
        }
        unset($options['tools'], $options['allowed_tools']);

        $command = [$this->getCliBinary(), 'exec', '--json'];

        foreach ($options as $key => $value) {
            $flag = '--'.str_replace('_', '-', $key);

            if (\is_array($value)) {
                foreach ($value as $item) {
                    $command[] = $flag;
                    $command[] = (string) $item;
                }
            } elseif (true === $value) {
                $command[] = $flag;
            } elseif (false !== $value) {
                $command[] = $flag;
                $command[] = (string) $value;
            }
        }

        $command[] = $prompt;

        return $command;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if ([] === $data) {
            throw new RuntimeException('Codex CLI did not return any result.');
        }

        if ('error' === ($data['type'] ?? '')) {
            throw new RuntimeException(\sprintf('Codex CLI error: "%s"', $data['message'] ?? 'Unknown error'));
        }

        $text = $data['item']['text'] ?? null;

        if (null === $text) {
            throw new RuntimeException('Codex CLI result does not contain a text field.');
        }

        $results = [];
        foreach ($data['tool_calls'] ?? [] as $toolCall) {
            $results[] = $this->convertExecutedToolCall($toolCall);
        }

        $results[] = new TextResult($text);

        if (1 === \count($results)) {
            return $results[0];
        }

        return new MultiPartResult($results);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    /**
     * The CLI already ran the tool, so the call is reported as resolved, not as a tool call for the caller to run.
     *
     * @param array{id: string, name: string, arguments?: array<string, mixed>, server?: string|null, output?: string|null, error?: string|null, status?: string|null} $toolCall
     */
    private function convertExecutedToolCall(array $toolCall): McpCallResult|CustomToolCallResult
    {
        $arguments = json_encode((object) ($toolCall['arguments'] ?? []), \JSON_THROW_ON_ERROR);

        if (null !== ($toolCall['server'] ?? null)) {
            return new McpCallResult($toolCall['server'], $toolCall['name'], $arguments, $toolCall['output'] ?? null, $toolCall['error'] ?? null, $toolCall['id'], $toolCall['status'] ?? null);
        }

        return new CustomToolCallResult($toolCall['name'], $arguments, $toolCall['id'], $toolCall['status'] ?? null);
    }

    private function getCliBinary(): string
    {
        $binary = $this->cliBinary ?? (new ExecutableFinder())->find('codex');

        if (null === $binary || !is_executable($binary)) {
            throw new CliNotFoundException();
        }

        return $binary;
    }

    /**
     * @param array<string|int, mixed>|string $payload
     */
    private function extractPrompt(array|string $payload): string
    {
        if (\is_string($payload)) {
            return $payload;
        }

        return (string) ($payload['prompt'] ?? json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $inTurn = false;

        foreach ($result->getDataStream() as $data) {
            $type = $data['type'] ?? '';

            if ('turn.started' === $type) {
                $inTurn = true;
            }

            if ('turn.completed' === $type || 'turn.failed' === $type) {
                $inTurn = false;
            }

            if ('item.completed' === $type
                && 'agent_message' === ($data['item']['type'] ?? '')
                && isset($data['item']['text'])
            ) {
                yield new TextDelta($data['item']['text']);
            }

            if ('turn.completed' === $type && isset($data['usage'])) {
                yield new TokenUsage(
                    promptTokens: $data['usage']['input_tokens'] ?? null,
                    completionTokens: $data['usage']['output_tokens'] ?? null,
                    cachedTokens: $data['usage']['cached_input_tokens'] ?? null,
                );
            }
        }

        if ($inTurn) {
            throw new IncompleteStreamException('Codex stream ended before turn.completed.');
        }
    }
}
