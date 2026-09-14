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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
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
     * @var array<string, string>
     */
    private const OPTION_FLAG_MAP = [
        'tools' => '--allowedTools',
        'allowed_tools' => '--allowedTools',
    ];

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

        $command = [$this->getCliBinary(), 'exec', '--json'];

        foreach ($options as $key => $value) {
            $flag = self::OPTION_FLAG_MAP[$key] ?? '--'.str_replace('_', '-', $key);

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
            $results[] = new ToolCallResult([new ToolCall(
                $toolCall['id'],
                $toolCall['name'],
                $toolCall['arguments'] ?? [],
            )]);
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
        foreach ($result->getDataStream() as $data) {
            $type = $data['type'] ?? '';

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
    }
}
