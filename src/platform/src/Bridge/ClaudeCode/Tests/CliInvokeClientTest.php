<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\ClaudeCode\ClaudeCode;
use Symfony\AI\Platform\Bridge\ClaudeCode\CliInvokeClient;
use Symfony\AI\Platform\Bridge\ClaudeCode\Exception\CliNotFoundException;
use Symfony\AI\Platform\Bridge\ClaudeCode\RawProcessResult;
use Symfony\AI\Platform\Bridge\ClaudeCode\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\MalformedToolCallException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CliInvokeClientTest extends TestCase
{
    private static string $binary;

    public static function setUpBeforeClass(): void
    {
        self::$binary = file_exists('/usr/bin/echo') ? '/usr/bin/echo' : '/bin/echo';
    }

    public function testSupportsClaudeCode()
    {
        $client = new CliInvokeClient();

        $this->assertTrue($client->supports(new ClaudeCode('sonnet')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $client = new CliInvokeClient();

        $this->assertFalse($client->supports(new Claude('claude-3-5-sonnet-latest')));
    }

    public function testThrowsExceptionWhenCliNotFound()
    {
        $client = new CliInvokeClient('/non/existent/binary/that/does/not/exist');

        $this->expectException(CliNotFoundException::class);
        $this->expectExceptionMessage('The "claude" CLI binary was not found.');

        $client->buildCommand('Hello');
    }

    public function testBuildCommandWithDefaults()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello, World!');

        $this->assertSame([self::$binary, '--output-format', 'stream-json', '--verbose', '--include-partial-messages', '-p', 'Hello, World!'], $command);
    }

    public function testBuildCommandWithSystemPrompt()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['system_prompt' => 'You are a pirate']);

        $this->assertContains('--system-prompt', $command);
        $this->assertContains('You are a pirate', $command);
    }

    public function testBuildCommandWithModel()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['model' => 'sonnet']);

        $this->assertContains('--model', $command);
        $this->assertContains('sonnet', $command);
    }

    public function testBuildCommandWithMaxTurns()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['max_turns' => 3]);

        $this->assertContains('--max-turns', $command);
        $this->assertContains('3', $command);
    }

    public function testBuildCommandWithPermissionMode()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['permission_mode' => 'plan']);

        $this->assertContains('--permission-mode', $command);
        $this->assertContains('plan', $command);
    }

    public function testBuildCommandWithAllowedTools()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['allowed_tools' => ['Bash', 'Read']]);

        $this->assertSame(2, \count(array_keys($command, '--allowedTools', true)));
        $this->assertContains('Bash', $command);
        $this->assertContains('Read', $command);
    }

    public function testBuildCommandWithMcpConfig()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['mcp_config' => '/path/to/config.json']);

        $this->assertContains('--mcp-config', $command);
        $this->assertContains('/path/to/config.json', $command);
    }

    public function testBuildCommandWithAllOptions()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', [
            'system_prompt' => 'Be helpful',
            'model' => 'opus',
            'max_turns' => 5,
            'permission_mode' => 'plan',
            'allowed_tools' => ['Bash'],
            'mcp_config' => '/path/to/config.json',
        ]);

        $expected = [
            self::$binary,
            '--output-format', 'stream-json', '--verbose', '--include-partial-messages',
            '--system-prompt', 'Be helpful',
            '--model', 'opus',
            '--max-turns', '5',
            '--permission-mode', 'plan',
            '--allowedTools', 'Bash',
            '--mcp-config', '/path/to/config.json',
            '-p', 'Hello',
        ];

        $this->assertSame($expected, $command);
    }

    public function testBuildCommandRewritesToolsToAllowedTools()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['tools' => ['Bash', 'Read']]);

        $this->assertNotContains('--tools', $command);
        $this->assertSame(2, \count(array_keys($command, '--allowedTools', true)));
        $this->assertContains('Bash', $command);
        $this->assertContains('Read', $command);
    }

    public function testBuildCommandPassesUnknownOptionsAsFlags()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['custom_flag' => 'value']);

        $this->assertContains('--custom-flag', $command);
        $this->assertContains('value', $command);
    }

    public function testBuildCommandHandlesBooleanTrueAsFlag()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['no_cache' => true]);

        $this->assertContains('--no-cache', $command);
    }

    public function testBuildCommandSkipsBooleanFalse()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['no_cache' => false]);

        $this->assertNotContains('--no-cache', $command);
    }

    public function testRequestReturnsRawProcessResult()
    {
        $client = new CliInvokeClient(self::$binary);
        $model = new ClaudeCode('sonnet');

        $result = $client->request($model, 'Hello');

        $this->assertInstanceOf(RawProcessResult::class, $result);
    }

    public function testRequestPassesModelNameToCommand()
    {
        $client = new CliInvokeClient(self::$binary);
        $model = new ClaudeCode('opus');

        $result = $client->request($model, 'Hello');
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('--model', $commandLine);
        $this->assertStringContainsString('opus', $commandLine);
    }

    public function testRequestUsesPromptFromNormalizedPayload()
    {
        $client = new CliInvokeClient(self::$binary);
        $model = new ClaudeCode('sonnet');

        $payload = ['prompt' => 'What is PHP?', 'system_prompt' => 'You are helpful.'];

        $result = $client->request($model, $payload);
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('What is PHP?', $commandLine);
        $this->assertStringContainsString('--system-prompt', $commandLine);
        $this->assertStringContainsString('You are helpful.', $commandLine);
    }

    public function testRequestUsesStringPayloadDirectly()
    {
        $client = new CliInvokeClient(self::$binary);
        $model = new ClaudeCode('sonnet');

        $result = $client->request($model, 'Hello, World!');
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('Hello, World!', $commandLine);
    }

    public function testConvertTextResult()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Hello, World!',
        ]);

        $result = $client->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, World!', $result->getContent());
    }

    public function testConvertReturnsMultiPartResultWithToolCalls()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Hello, World!',
            'tool_calls' => [
                [
                    'id' => 'toolu_123',
                    'name' => 'symfony_logs',
                    'arguments' => ['channel' => 'app'],
                ],
            ],
        ]);

        $result = $client->convert($rawResult);

        $this->assertInstanceOf(MultiPartResult::class, $result);

        $parts = $result->getContent();
        $this->assertCount(2, $parts);

        $this->assertInstanceOf(ToolCallResult::class, $parts[0]);
        $toolCalls = $parts[0]->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_123', $toolCalls[0]->getId());
        $this->assertSame('symfony_logs', $toolCalls[0]->getName());
        $this->assertSame(['channel' => 'app'], $toolCalls[0]->getArguments());

        $this->assertInstanceOf(TextResult::class, $parts[1]);
        $this->assertSame('Hello, World!', $parts[1]->getContent());
    }

    public function testConvertThrowsOnEmptyData()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI did not return any result.');

        $client->convert($rawResult);
    }

    public function testConvertThrowsOnErrorResult()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'is_error' => true,
            'result' => 'Something went wrong',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI error: "Something went wrong"');

        $client->convert($rawResult);
    }

    public function testConvertThrowsOnMissingResultField()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI result does not contain a "result" field.');

        $client->convert($rawResult);
    }

    public function testConvertStreamingReturnsStreamResult()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hello']]],
                ['type' => 'result', 'result' => 'Hello'],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
    }

    public function testConvertStreamingYieldsTextDeltas()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hello, ']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'World!']]],
                ['type' => 'result', 'result' => 'Hello, World!'],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello, ', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame('World!', $chunks[1]->getText());
    }

    public function testConvertStreamingIgnoresNonTextEvents()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'system', 'subtype' => 'init'],
                ['type' => 'stream_event', 'event' => ['type' => 'message_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hello']]],
                ['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => 'Hello']]]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop']],
                ['type' => 'stream_event', 'event' => ['type' => 'message_stop']],
                ['type' => 'result', 'result' => 'Hello'],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
    }

    public function testConvertStreamingThrowsOnErrorEvent()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'message_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hello']]],
                ['type' => 'stream_event', 'event' => ['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']]],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Overloaded');

        iterator_to_array($result->getContent(), false);
    }

    public function testConvertStreamingThrowsOnErrorEventWithoutMessage()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'error']],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown Claude Code stream error.');

        iterator_to_array($result->getContent(), false);
    }

    public function testConvertStreamingYieldsToolCallDeltas()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'message_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_01ABC123', 'name' => 'get_weather']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"loc']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => 'ation": "']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => 'Berlin"}']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 0]],
                ['type' => 'stream_event', 'event' => ['type' => 'message_stop']],
                ['type' => 'result', 'result' => ''],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(5, $chunks);

        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertSame('toolu_01ABC123', $chunks[0]->getId());
        $this->assertSame('get_weather', $chunks[0]->getName());

        $this->assertInstanceOf(ToolInputDelta::class, $chunks[1]);
        $this->assertSame('toolu_01ABC123', $chunks[1]->getId());
        $this->assertSame('{"loc', $chunks[1]->getPartialJson());
        $this->assertInstanceOf(ToolInputDelta::class, $chunks[2]);
        $this->assertSame('ation": "', $chunks[2]->getPartialJson());
        $this->assertInstanceOf(ToolInputDelta::class, $chunks[3]);
        $this->assertSame('Berlin"}', $chunks[3]->getPartialJson());

        $this->assertInstanceOf(ToolCallComplete::class, $chunks[4]);
        $toolCalls = $chunks[4]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_01ABC123', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'Berlin'], $toolCalls[0]->getArguments());
    }

    public function testConvertStreamingThrowsClearExceptionForMalformedToolCallArguments()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'message_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_01ABC123', 'name' => 'get_weather']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"city":Berlin}']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 0]],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $this->expectException(MalformedToolCallException::class);
        $this->expectExceptionMessage('Claude Code returned malformed JSON arguments for the "get_weather" tool: "Syntax error"');

        iterator_to_array($result->getContent(), false);
    }

    public function testConvertStreamingThrowsWhenMessageStopIsMissing()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'message_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_01ABC123', 'name' => 'get_weather']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"location":"Berlin"}']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 0]],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $this->expectException(IncompleteStreamException::class);
        $this->expectExceptionMessage('Claude Code stream ended before message_stop.');

        iterator_to_array($result->getContent());
    }

    public function testConvertStreamingThrowsWhenSecondMessageIsTruncated()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'message_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'First turn']]],
                ['type' => 'stream_event', 'event' => ['type' => 'message_stop']],
                ['type' => 'stream_event', 'event' => ['type' => 'message_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Second turn']]],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $this->expectException(IncompleteStreamException::class);
        $this->expectExceptionMessage('Claude Code stream ended before message_stop.');

        iterator_to_array($result->getContent());
    }

    public function testConvertStreamingYieldsToolCallWithEmptyInput()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_empty', 'name' => 'list_files']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 0]],
                ['type' => 'stream_event', 'event' => ['type' => 'message_stop']],
                ['type' => 'result', 'result' => ''],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);

        $this->assertInstanceOf(ToolCallComplete::class, $chunks[1]);
        $toolCalls = $chunks[1]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_empty', $toolCalls[0]->getId());
        $this->assertSame([], $toolCalls[0]->getArguments());
    }

    public function testConvertStreamingInterleavesTextAndToolCallDeltas()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Let me check.']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_01XYZ789', 'name' => 'get_weather']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"location":"Paris"}']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 1]],
                ['type' => 'stream_event', 'event' => ['type' => 'message_stop']],
                ['type' => 'result', 'result' => 'Let me check.'],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(4, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Let me check.', $chunks[0]->getText());
        $this->assertInstanceOf(ToolCallStart::class, $chunks[1]);
        $this->assertInstanceOf(ToolInputDelta::class, $chunks[2]);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[3]);
        $this->assertSame(['location' => 'Paris'], $chunks[3]->getToolCalls()[0]->getArguments());
    }

    public function testConvertStreamingYieldsMultipleToolCallsInOrder()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_a', 'name' => 'first']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"x":1}']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 0]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_b', 'name' => 'second']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"y":2}']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 1]],
                ['type' => 'stream_event', 'event' => ['type' => 'message_stop']],
                ['type' => 'result', 'result' => ''],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $toolCallComplete = $chunks[\count($chunks) - 1];
        $this->assertInstanceOf(ToolCallComplete::class, $toolCallComplete);
        $toolCalls = $toolCallComplete->getToolCalls();
        $this->assertCount(2, $toolCalls);
        $this->assertSame('toolu_a', $toolCalls[0]->getId());
        $this->assertSame(['x' => 1], $toolCalls[0]->getArguments());
        $this->assertSame('toolu_b', $toolCalls[1]->getId());
        $this->assertSame(['y' => 2], $toolCalls[1]->getArguments());
    }

    public function testGetTokenUsageExtractor()
    {
        $client = new CliInvokeClient();

        $this->assertInstanceOf(TokenUsageExtractor::class, $client->getTokenUsageExtractor());
    }
}
