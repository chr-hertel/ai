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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\ClaudeCode\ClaudeCode;
use Symfony\AI\Platform\Bridge\ClaudeCode\CliInvokeClient;
use Symfony\AI\Platform\Bridge\ClaudeCode\Contract\ClaudeCodeContract;
use Symfony\AI\Platform\Bridge\ClaudeCode\Exception\CliNotFoundException;
use Symfony\AI\Platform\Bridge\ClaudeCode\RawProcessResult;
use Symfony\AI\Platform\Bridge\ClaudeCode\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\MalformedToolCallException;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\CustomToolCallResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\McpCallResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Process\Process;

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

    public function testBuildCommandRestrictsTools()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['tools' => ['Bash', 'Read']]);

        $expected = [
            self::$binary,
            '--output-format', 'stream-json', '--verbose', '--include-partial-messages',
            '--tools', 'Bash',
            '--tools', 'Read',
            '-p', 'Hello',
        ];

        $this->assertSame($expected, $command);
    }

    public function testBuildCommandWithEmptyToolsDisablesAllTools()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['tools' => []]);

        $expected = [
            self::$binary,
            '--output-format', 'stream-json', '--verbose', '--include-partial-messages',
            '--tools', '',
            '-p', 'Hello',
        ];

        $this->assertSame($expected, $command);
    }

    public function testBuildCommandKeepsToolsAndAllowedToolsSeparate()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['tools' => ['Bash', 'Read'], 'allowed_tools' => ['Read']]);

        $expected = [
            self::$binary,
            '--output-format', 'stream-json', '--verbose', '--include-partial-messages',
            '--tools', 'Bash',
            '--tools', 'Read',
            '--allowedTools', 'Read',
            '-p', 'Hello',
        ];

        $this->assertSame($expected, $command);
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

    public function testRequestWithToolDefinitionsThrowsException()
    {
        $client = new CliInvokeClient(self::$binary);
        $model = new ClaudeCode('sonnet');

        $this->expectException(MissingModelSupportException::class);
        $this->expectExceptionMessage('Model "sonnet" (Symfony\AI\Platform\Bridge\ClaudeCode\ClaudeCode) does not support "tool calling".');

        $client->request($model, 'Hello', ['tools' => self::toolDefinitions($model)]);
    }

    public function testRequestWithToolNamesAndDefinitionsThrowsException()
    {
        $client = new CliInvokeClient(self::$binary);
        $model = new ClaudeCode('sonnet');

        $this->expectException(MissingModelSupportException::class);

        $client->request($model, 'Hello', ['tools' => ['Read', ...self::toolDefinitions($model)]]);
    }

    /**
     * @param list<string> $tools
     */
    #[TestWith([['Read'], "'--tools' 'Read'"])]
    #[TestWith([[], "'--tools' \"\""])]
    public function testRequestPassesBuiltInToolNames(array $tools, string $expectedFlag)
    {
        $client = new CliInvokeClient(self::$binary);

        $result = $client->request(new ClaudeCode('sonnet'), 'Hello', ['tools' => $tools]);

        $this->assertStringContainsString($expectedFlag, $result->getObject()->getCommandLine());
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

    public function testConvertReportsBuiltInToolsTheCliRanAsCustomToolCalls()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Hello, World!',
            'tool_calls' => [
                ['id' => 'toolu_1', 'name' => 'Read', 'arguments' => ['file_path' => 'README.md'], 'output' => '# Readme', 'is_error' => false],
                ['id' => 'toolu_2', 'name' => 'Bash', 'arguments' => ['command' => 'false'], 'output' => 'Exit code 1', 'is_error' => true],
                ['id' => 'toolu_3', 'name' => 'Glob', 'arguments' => [], 'output' => null, 'is_error' => null],
            ],
        ]);

        $result = $client->convert($rawResult);

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertNull($result->asToolCallResult());
        $this->assertSame('Hello, World!', $result->asText());

        $parts = $result->getContent();
        $this->assertCount(4, $parts);

        $this->assertInstanceOf(CustomToolCallResult::class, $parts[0]);
        $this->assertSame(['toolu_1', 'Read', '{"file_path":"README.md"}', 'completed'], [$parts[0]->getId(), $parts[0]->getName(), $parts[0]->getInput(), $parts[0]->getStatus()]);

        $this->assertInstanceOf(CustomToolCallResult::class, $parts[1]);
        $this->assertSame(['toolu_2', 'Bash', '{"command":"false"}', 'failed'], [$parts[1]->getId(), $parts[1]->getName(), $parts[1]->getInput(), $parts[1]->getStatus()]);

        $this->assertInstanceOf(CustomToolCallResult::class, $parts[2]);
        $this->assertSame(['toolu_3', 'Glob', '{}', null], [$parts[2]->getId(), $parts[2]->getName(), $parts[2]->getInput(), $parts[2]->getStatus()]);

        $this->assertInstanceOf(TextResult::class, $parts[3]);
        $this->assertSame('Hello, World!', $parts[3]->getContent());
    }

    public function testConvertReportsMcpToolsTheCliRanAsMcpCalls()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Hello, World!',
            'tool_calls' => [
                ['id' => 'toolu_1', 'name' => 'mcp__symfony_ai_mate__symfony_logs', 'arguments' => ['channel' => 'app'], 'output' => '{"entries":[]}', 'is_error' => false],
                ['id' => 'toolu_2', 'name' => 'mcp__github__get_issue', 'arguments' => ['number' => 1], 'output' => 'Not Found', 'is_error' => true],
            ],
        ]);

        $result = $client->convert($rawResult);

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertNull($result->asToolCallResult());
        $this->assertSame('Hello, World!', $result->asText());

        $parts = $result->getContent();
        $this->assertCount(3, $parts);

        $this->assertInstanceOf(McpCallResult::class, $parts[0]);
        $this->assertSame(
            ['symfony_ai_mate', 'symfony_logs', '{"channel":"app"}', '{"entries":[]}', null, 'toolu_1', 'completed'],
            [$parts[0]->getServerLabel(), $parts[0]->getName(), $parts[0]->getArguments(), $parts[0]->getContent(), $parts[0]->getError(), $parts[0]->getId(), $parts[0]->getStatus()],
        );

        $this->assertInstanceOf(McpCallResult::class, $parts[1]);
        $this->assertSame(
            ['github', 'get_issue', '{"number":1}', null, 'Not Found', 'toolu_2', 'failed'],
            [$parts[1]->getServerLabel(), $parts[1]->getName(), $parts[1]->getArguments(), $parts[1]->getContent(), $parts[1]->getError(), $parts[1]->getId(), $parts[1]->getStatus()],
        );
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

    public function testConvertStreamingYieldsToolCallProgressWithoutToolCallComplete()
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

        $this->assertCount(4, $chunks);

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

    public function testConvertStreamingYieldsToolCallStartWithEmptyInput()
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

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertSame('toolu_empty', $chunks[0]->getId());
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

        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Let me check.', $chunks[0]->getText());
        $this->assertInstanceOf(ToolCallStart::class, $chunks[1]);
        $this->assertInstanceOf(ToolInputDelta::class, $chunks[2]);
        $this->assertSame('{"location":"Paris"}', $chunks[2]->getPartialJson());
    }

    public function testConvertStreamingYieldsMultipleToolCallStartsInOrder()
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

        $starts = array_values(array_filter($chunks, static fn ($chunk) => $chunk instanceof ToolCallStart));
        $this->assertCount(2, $starts);
        $this->assertSame('toolu_a', $starts[0]->getId());
        $this->assertSame('toolu_b', $starts[1]->getId());
        $this->assertSame([], array_filter($chunks, static fn ($chunk) => $chunk instanceof ToolCallComplete));
    }

    public function testGetTokenUsageExtractor()
    {
        $client = new CliInvokeClient();

        $this->assertInstanceOf(TokenUsageExtractor::class, $client->getTokenUsageExtractor());
    }

    /**
     * Recorded from Claude Code 2.1.316 reading README.md with its built-in Read tool.
     */
    public function testConvertRecordedRunWithBuiltInToolReportsTheToolAsResolved()
    {
        $result = (new CliInvokeClient())->convert(new RawProcessResult(self::recordedRun('read_tool.jsonl')));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertNull($result->asToolCallResult());
        $this->assertSame('The project codename is **Lantern**.', $result->asText());

        $parts = $result->getContent();
        $this->assertCount(2, $parts);
        $this->assertInstanceOf(CustomToolCallResult::class, $parts[0]);
        $this->assertSame(
            ['toolu_01', 'Read', '{"file_path":"\/tmp\/lantern\/README.md"}', 'completed'],
            [$parts[0]->getId(), $parts[0]->getName(), $parts[0]->getInput(), $parts[0]->getStatus()],
        );
        $this->assertInstanceOf(TextResult::class, $parts[1]);
    }

    /**
     * Recorded from Claude Code 2.1.316 calling the read_file tool of an MCP server.
     */
    public function testConvertRecordedRunWithMcpToolReportsTheMcpCall()
    {
        $result = (new CliInvokeClient())->convert(new RawProcessResult(self::recordedRun('mcp_tool.jsonl')));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertNull($result->asToolCallResult());
        $this->assertSame('The project codename is Lantern.', $result->asText());

        $parts = $result->getContent();
        $this->assertCount(2, $parts);
        $this->assertInstanceOf(McpCallResult::class, $parts[0]);
        $this->assertSame(
            ['files', 'read_file', '{"file_path":"\/tmp\/lantern\/README.md"}', "# Lantern\n\nThe project codename is Lantern. It has one rule: keep answers short.\n", null, 'toolu_01', 'completed'],
            [$parts[0]->getServerLabel(), $parts[0]->getName(), $parts[0]->getArguments(), $parts[0]->getContent(), $parts[0]->getError(), $parts[0]->getId(), $parts[0]->getStatus()],
        );
        $this->assertInstanceOf(TextResult::class, $parts[1]);
    }

    public function testConvertRecordedRunReportsMcpServersByTheirConfiguredNames()
    {
        $result = (new CliInvokeClient())->convert(new RawProcessResult(self::recordedRun('mcp_tool_server_names.jsonl')));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertSame('Lantern.', $result->asText());

        $parts = $result->getContent();
        $this->assertCount(7, $parts);
        $this->assertInstanceOf(CustomToolCallResult::class, $parts[0]);
        $this->assertSame('ToolSearch', $parts[0]->getName());
        $mcpCalls = \array_slice($parts, 1, 5);
        $this->assertContainsOnlyInstancesOf(McpCallResult::class, $mcpCalls);
        $this->assertSame(
            [['file__store', 'read_file'], ['my files.v2', 'read_file'], ['café', 'read_file'], ['srv😀x', 'read_file'], ['claude.ai My  Drive!', 'read_file']],
            array_map(static fn (McpCallResult $call): array => [$call->getServerLabel(), $call->getName()], $mcpCalls),
        );
        $this->assertInstanceOf(TextResult::class, $parts[6]);
    }

    public function testConvertSplitsTheNameOfAnMcpToolCallWithoutAKnownServerAtTheFirstSeparator()
    {
        $result = (new CliInvokeClient())->convert(new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Done.',
            'tool_calls' => [['id' => 'toolu_1', 'name' => 'mcp__file__store__read_file', 'arguments' => [], 'output' => 'x', 'is_error' => false]],
        ]));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $part = $result->getContent()[0];
        $this->assertInstanceOf(McpCallResult::class, $part);
        $this->assertSame(['file', 'store__read_file'], [$part->getServerLabel(), $part->getName()]);
    }

    public function testConvertRecordedStreamDeliversTheAnswerWithoutToolCallComplete()
    {
        $events = array_map(static fn (string $line): array => json_decode($line, true, flags: \JSON_THROW_ON_ERROR), file(__DIR__.'/Fixtures/read_tool.jsonl'));

        $chunks = iterator_to_array((new CliInvokeClient())->convert(new InMemoryRawResult([], $events), ['stream' => true])->getContent(), false);

        $this->assertSame([], array_filter($chunks, static fn ($chunk) => $chunk instanceof ToolCallComplete));
        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertSame('Read', $chunks[0]->getName());
        $this->assertSame('The project codename is **Lantern**.', implode('', array_map(static fn (TextDelta $delta): string => $delta->getText(), array_filter($chunks, static fn ($chunk) => $chunk instanceof TextDelta))));
    }

    /**
     * @return array<string, mixed>
     */
    private static function toolDefinitions(ClaudeCode $model): array
    {
        return ClaudeCodeContract::create()->createToolOption([
            new Tool(new ExecutionReference(self::class), 'clock', 'Provides the current date and time.'),
        ], $model);
    }

    private static function recordedRun(string $fixture): Process
    {
        $process = new Process(['php', '-r', 'readfile($argv[1]);', __DIR__.'/Fixtures/'.$fixture]);
        $process->start();

        return $process;
    }
}
