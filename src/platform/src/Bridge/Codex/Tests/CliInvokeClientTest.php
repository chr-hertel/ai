<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex\Tests;

use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Codex\CliInvokeClient;
use Symfony\AI\Platform\Bridge\Codex\Codex;
use Symfony\AI\Platform\Bridge\Codex\Exception\CliNotFoundException;
use Symfony\AI\Platform\Bridge\Codex\RawProcessResult;
use Symfony\AI\Platform\Bridge\Codex\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class CliInvokeClientTest extends TestCase
{
    private static string $binary;

    public static function setUpBeforeClass(): void
    {
        self::$binary = file_exists('/usr/bin/echo') ? '/usr/bin/echo' : '/bin/echo';
    }

    public function testSupportsCodex()
    {
        $client = new CliInvokeClient();

        $this->assertTrue($client->supports(new Codex('gpt-5-codex')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $client = new CliInvokeClient();

        $this->assertFalse($client->supports(new Model('sonnet')));
    }

    public function testThrowsExceptionWhenCliNotFound()
    {
        $client = new CliInvokeClient('/non/existent/binary/that/does/not/exist');

        $this->expectException(CliNotFoundException::class);
        $this->expectExceptionMessage('The "codex" CLI binary was not found.');

        $client->buildCommand('Hello');
    }

    public function testBuildCommandWithDefaults()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello, World!');

        $this->assertSame([self::$binary, 'exec', '--json', 'Hello, World!'], $command);
    }

    public function testBuildCommandWithModel()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['model' => 'gpt-5-codex']);

        $this->assertContains('--model', $command);
        $this->assertContains('gpt-5-codex', $command);
    }

    public function testBuildCommandWithSandbox()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['sandbox' => 'workspace-write']);

        $this->assertContains('--sandbox', $command);
        $this->assertContains('workspace-write', $command);
    }

    public function testBuildCommandWithImage()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['image' => '/path/to/image.png']);

        $this->assertContains('--image', $command);
        $this->assertContains('/path/to/image.png', $command);
    }

    public function testBuildCommandWithAllowedTools()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['allowed_tools' => ['Bash', 'Read']]);

        $this->assertSame(2, \count(array_keys($command, '--allowedTools', true)));
        $this->assertContains('Bash', $command);
        $this->assertContains('Read', $command);
    }

    public function testBuildCommandWithAllOptions()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', [
            'model' => 'gpt-5-codex',
            'sandbox' => 'workspace-write',
            'allowed_tools' => ['Bash'],
        ]);

        $expected = [
            self::$binary,
            'exec', '--json',
            '--model', 'gpt-5-codex',
            '--sandbox', 'workspace-write',
            '--allowedTools', 'Bash',
            'Hello',
        ];

        $this->assertSame($expected, $command);
    }

    #[IgnoreDeprecations]
    public function testBuildCommandTriggersDeprecationForAskForApproval()
    {
        $client = new CliInvokeClient(self::$binary);

        $this->expectUserDeprecationMessageMatches('/ask_for_approval/');

        $command = $client->buildCommand('Hello', ['ask_for_approval' => 'on-request']);

        $this->assertNotContains('--ask-for-approval', $command);
        $this->assertSame([self::$binary, 'exec', '--json', 'Hello'], $command);
    }

    public function testBuildCommandWithDangerouslyBypassApprovalsAndSandbox()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', [
            'dangerously_bypass_approvals_and_sandbox' => true,
        ]);

        $this->assertSame([
            self::$binary,
            'exec', '--json',
            '--dangerously-bypass-approvals-and-sandbox',
            'Hello',
        ], $command);
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

        $command = $client->buildCommand('Hello', ['search' => true]);

        $this->assertContains('--search', $command);
    }

    public function testBuildCommandSkipsBooleanFalse()
    {
        $client = new CliInvokeClient(self::$binary);

        $command = $client->buildCommand('Hello', ['search' => false]);

        $this->assertNotContains('--search', $command);
    }

    public function testRequestReturnsRawProcessResult()
    {
        $client = new CliInvokeClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $result = $client->request($model, 'Hello');

        $this->assertInstanceOf(RawProcessResult::class, $result);
    }

    public function testRequestPassesModelNameToCommand()
    {
        $client = new CliInvokeClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $result = $client->request($model, 'Hello');
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('--model', $commandLine);
        $this->assertStringContainsString('gpt-5-codex', $commandLine);
    }

    public function testRequestUsesPromptFromNormalizedPayload()
    {
        $client = new CliInvokeClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $payload = ['prompt' => 'What is PHP?', 'system_prompt' => 'You are helpful.'];

        $result = $client->request($model, $payload);
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('What is PHP?', $commandLine);
        $this->assertStringContainsString('You are helpful.', $commandLine);
    }

    public function testRequestUsesStringPayloadDirectly()
    {
        $client = new CliInvokeClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $result = $client->request($model, 'Hello, World!');
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('Hello, World!', $commandLine);
    }

    public function testRequestAddsDefaultSandbox()
    {
        $client = new CliInvokeClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $result = $client->request($model, 'Hello');
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('--sandbox', $commandLine);
        $this->assertStringContainsString('read-only', $commandLine);
    }

    public function testRequestPrependsSystemPromptToPrompt()
    {
        $client = new CliInvokeClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $payload = ['prompt' => 'What is PHP?', 'system_prompt' => 'You are a pirate.'];
        $result = $client->request($model, $payload);
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('[System]', $commandLine);
        $this->assertStringContainsString('You are a pirate.', $commandLine);
        $this->assertStringContainsString('[User]', $commandLine);
        $this->assertStringContainsString('What is PHP?', $commandLine);
        // system_prompt should not be passed as a flag
        $this->assertStringNotContainsString('--system-prompt', $commandLine);
    }

    public function testConvertTextResult()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult([
            'type' => 'item.completed',
            'item' => ['type' => 'agent_message', 'text' => 'Hello, World!'],
        ]);

        $result = $client->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, World!', $result->getContent());
    }

    public function testConvertReturnsMultiPartResultWithToolCalls()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult([
            'type' => 'item.completed',
            'item' => ['type' => 'agent_message', 'text' => 'Hello, World!'],
            'tool_calls' => [
                [
                    'id' => 'call-1',
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
        $this->assertSame('call-1', $toolCalls[0]->getId());
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
        $this->expectExceptionMessage('Codex CLI did not return any result.');

        $client->convert($rawResult);
    }

    public function testConvertThrowsOnErrorResult()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult([
            'type' => 'error',
            'message' => 'Something went wrong',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Codex CLI error: "Something went wrong"');

        $client->convert($rawResult);
    }

    public function testConvertThrowsOnMissingTextField()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult([
            'type' => 'item.completed',
            'item' => ['type' => 'agent_message'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Codex CLI result does not contain a text field.');

        $client->convert($rawResult);
    }

    public function testConvertStreamingReturnsStreamResult()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Hello']],
                ['type' => 'turn.completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertInstanceOf(TokenUsage::class, $chunks[1]);
        $this->assertSame(10, $chunks[1]->getPromptTokens());
        $this->assertSame(5, $chunks[1]->getCompletionTokens());
    }

    public function testConvertStreamingYieldsMultipleAgentMessages()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'First part.']],
                ['type' => 'item.completed', 'item' => ['type' => 'command_execution', 'command' => 'ls']],
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Second part.']],
                ['type' => 'turn.completed', 'usage' => ['input_tokens' => 50, 'output_tokens' => 20]],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('First part.', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame('Second part.', $chunks[1]->getText());
        $this->assertInstanceOf(TokenUsage::class, $chunks[2]);
        $this->assertSame(50, $chunks[2]->getPromptTokens());
        $this->assertSame(20, $chunks[2]->getCompletionTokens());
    }

    public function testConvertStreamingIgnoresNonAgentEvents()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'thread.started', 'thread_id' => 'test-123'],
                ['type' => 'turn.started'],
                ['type' => 'item.started', 'item' => ['type' => 'command_execution', 'status' => 'in_progress']],
                ['type' => 'item.completed', 'item' => ['type' => 'command_execution', 'command' => 'ls']],
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Hello']],
                ['type' => 'turn.completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertInstanceOf(TokenUsage::class, $chunks[1]);
    }

    public function testConvertStreamingSkipsTurnCompletedWithoutUsage()
    {
        $client = new CliInvokeClient();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Hello']],
                ['type' => 'turn.completed'],
            ],
        );

        $result = $client->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
    }

    public function testGetTokenUsageExtractor()
    {
        $client = new CliInvokeClient();

        $this->assertInstanceOf(TokenUsageExtractor::class, $client->getTokenUsageExtractor());
    }
}
