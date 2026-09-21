<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\ClaudeCode\Exception\CliNotFoundException;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\MalformedToolCallException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Spawns the Claude Code CLI as a subprocess and returns the result.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
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
        return $model instanceof ClaudeCode;
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

        $cwd = $options['cwd'] ?? $this->workingDirectory;
        unset($options['cwd'], $options['stream']);
        $command = $this->buildCommand($prompt, $options);

        $this->logger->info('Spawning Claude Code CLI subprocess.', [
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
        $command = [$this->getCliBinary(), '--output-format', 'stream-json', '--verbose', '--include-partial-messages'];

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

        $command[] = '-p';
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
            throw new RuntimeException('Claude Code CLI did not return any result.');
        }

        if (isset($data['is_error']) && true === $data['is_error']) {
            throw new RuntimeException(\sprintf('Claude Code CLI error: "%s"', $data['result'] ?? 'Unknown error'));
        }

        if (!isset($data['result'])) {
            throw new RuntimeException('Claude Code CLI result does not contain a "result" field.');
        }

        $results = [];
        foreach ($data['tool_calls'] ?? [] as $toolCall) {
            $results[] = new ToolCallResult([new ToolCall(
                $toolCall['id'],
                $toolCall['name'],
                $toolCall['arguments'] ?? [],
            )]);
        }

        $results[] = new TextResult($data['result']);

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
        $binary = $this->cliBinary ?? (new ExecutableFinder())->find('claude');

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
        $toolCalls = [];
        $currentToolCall = null;
        $currentToolCallJson = '';
        $inMessage = false;

        foreach ($result->getDataStream() as $data) {
            $type = $data['type'] ?? '';
            $eventType = $data['event']['type'] ?? null;

            if ('stream_event' === $type && 'error' === $eventType) {
                throw new RuntimeException($data['event']['error']['message'] ?? 'Unknown Claude Code stream error.');
            }

            if ('stream_event' === $type && 'message_start' === $eventType) {
                $inMessage = true;
            }

            // Handle streaming text deltas (wrapped in stream_event)
            if ('stream_event' === $type
                && 'content_block_delta' === $eventType
                && 'text_delta' === ($data['event']['delta']['type'] ?? '')
            ) {
                yield new TextDelta($data['event']['delta']['text']);
            }

            // Handle tool_use content block start
            if ('stream_event' === $type
                && 'content_block_start' === $eventType
                && 'tool_use' === ($data['event']['content_block']['type'] ?? '')
            ) {
                $currentToolCall = [
                    'id' => $data['event']['content_block']['id'],
                    'name' => $data['event']['content_block']['name'],
                ];
                $currentToolCallJson = '';
                yield new ToolCallStart($currentToolCall['id'], $currentToolCall['name']);
            }

            // Handle tool_use input JSON deltas
            if ('stream_event' === $type
                && 'content_block_delta' === $eventType
                && 'input_json_delta' === ($data['event']['delta']['type'] ?? '')
            ) {
                $partialJson = $data['event']['delta']['partial_json'] ?? '';
                $currentToolCallJson .= $partialJson;
                if (null !== $currentToolCall) {
                    yield new ToolInputDelta($currentToolCall['id'], $currentToolCall['name'], $partialJson);
                }
            }

            // Handle content block stop - finalize current tool call
            if ('stream_event' === $type
                && 'content_block_stop' === $eventType
                && null !== $currentToolCall
            ) {
                $input = [];
                if ('' !== $currentToolCallJson) {
                    try {
                        $input = json_decode($currentToolCallJson, true, flags: \JSON_THROW_ON_ERROR);
                    } catch (\JsonException $e) {
                        throw new MalformedToolCallException(\sprintf('Claude Code returned malformed JSON arguments for the "%s" tool: "%s"', $currentToolCall['name'], $e->getMessage()), 0, $e);
                    }
                }
                $toolCalls[] = new ToolCall(
                    $currentToolCall['id'],
                    $currentToolCall['name'],
                    $input
                );
                $currentToolCall = null;
                $currentToolCallJson = '';
            }

            // Handle message stop - yield tool calls if any were collected
            if ('stream_event' === $type
                && 'message_stop' === $eventType
            ) {
                $inMessage = false;

                if ([] !== $toolCalls) {
                    yield new ToolCallComplete($toolCalls);
                }
                $toolCalls = [];
            }
        }

        if ($inMessage) {
            throw new IncompleteStreamException('Claude Code stream ended before message_stop.');
        }
    }
}
