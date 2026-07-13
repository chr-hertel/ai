<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\Exception\InteractionRequiredException;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\InteractionResponse;
use Symfony\AI\Agent\Execution\Update\Interaction;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Store\InMemoryStore;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Exception\ToolInteractionException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;

final class AgentTest extends TestCase
{
    public function testConstructorInitializesWithDefaults()
    {
        $agent = new Agent($this->createMock(PlatformInterface::class), model: 'gpt-4o');

        $this->assertInstanceOf(AgentInterface::class, $agent);
    }

    public function testGetNameReturnsDefaultName()
    {
        $agent = new Agent($this->createMock(PlatformInterface::class), model: 'gpt-4');

        $this->assertSame('agent', $agent->getName());
    }

    public function testGetNameReturnsProvidedName()
    {
        $agent = new Agent($this->createMock(PlatformInterface::class), 'research', model: 'gpt-4');

        $this->assertSame('research', $agent->getName());
    }

    public function testCallReturnsResult()
    {
        $agent = new Agent(new InMemoryPlatform('Hi'), model: 'gpt-4');

        $result = $agent->call('Hello');

        $this->assertInstanceOf(ResultInterface::class, $result);
        $this->assertSame('Hi', $result->getContent());
    }

    public function testCallWithoutAnyModelThrows()
    {
        $agent = new Agent(new InMemoryPlatform('Hi'));

        $this->expectException(RuntimeException::class);

        $agent->call('Hello');
    }

    public function testCallPassesOptionsToInvoke()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));
        $options = ['temperature' => 0.7, 'max_tokens' => 100];
        $result = $this->createMock(ResultInterface::class);
        $response = new DeferredResult(new PlainConverter($result), $this->createMock(RawResultInterface::class), []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with('gpt-4', $messages, $options)
            ->willReturn($response);

        $agent = new Agent($platform, model: 'gpt-4');

        $this->assertSame($result, $agent->call($messages, options: $options));
    }

    public function testModelOptionOverridesDefaultModel()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));
        $result = $this->createMock(ResultInterface::class);
        $response = new DeferredResult(new PlainConverter($result), $this->createMock(RawResultInterface::class), []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o', $messages, ['model' => 'gpt-4o'])
            ->willReturn($response);

        $agent = new Agent($platform, model: 'gpt-4');
        $agent->call($messages, options: ['model' => 'gpt-4o']);
    }

    public function testCallAllowsAudioInput()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Audio('audio-data', 'audio/mp3')));
        $result = $this->createMock(ResultInterface::class);
        $response = new DeferredResult(new PlainConverter($result), $this->createMock(RawResultInterface::class), []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with('gpt-4', $messages, [])
            ->willReturn($response);

        $agent = new Agent($platform, model: 'gpt-4');

        $this->assertSame($result, $agent->call($messages));
    }

    public function testCallAllowsImageInput()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Image('image-data', 'image/png')));
        $result = $this->createMock(ResultInterface::class);
        $response = new DeferredResult(new PlainConverter($result), $this->createMock(RawResultInterface::class), []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with('gpt-4', $messages, [])
            ->willReturn($response);

        $agent = new Agent($platform, model: 'gpt-4');

        $this->assertSame($result, $agent->call($messages));
    }

    public function testRunYieldsExactlyOneResultUpdate()
    {
        $agent = new Agent(new InMemoryPlatform('Hi'), model: 'gpt-4');

        $execution = $agent->run('Hello');
        $this->assertInstanceOf(Execution::class, $execution);

        $updates = iterator_to_array($execution, false);
        $resultUpdates = array_filter($updates, static fn (object $update): bool => $update instanceof ResultUpdate);

        $this->assertCount(1, $resultUpdates);
    }

    public function testContextProcessorReceivesRequest()
    {
        $processor = new class implements ContextProcessorInterface {
            public ?AgentRequest $request = null;

            public static function supportedTypes(): array
            {
                return [];
            }

            public function process(AgentRequest $request, AgentContext $context): void
            {
                $this->request = $request;
            }
        };

        $agent = new Agent(new InMemoryPlatform('Hi'), model: 'gpt-4', contextProcessors: [$processor]);
        $agent->call('Hello');

        $this->assertInstanceOf(AgentRequest::class, $processor->request);
    }

    public function testInvalidContextProcessorThrowsException()
    {
        /** @phpstan-ignore-next-line argument.type */
        $agent = new Agent(new InMemoryPlatform('Hi'), model: 'gpt-4', contextProcessors: [new \stdClass()]);

        $this->expectException(InvalidArgumentException::class);

        $agent->call('Hello');
    }

    public function testInstructionIsInjectedAsSystemMessage()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $captured = null;
        $result = $this->createMock(ResultInterface::class);

        $platform->method('invoke')
            ->willReturnCallback(function (string $model, MessageBag $messages) use (&$captured, $result): DeferredResult {
                $captured = $messages;

                return new DeferredResult(new PlainConverter($result), $this->createMock(RawResultInterface::class), []);
            });

        $agent = new Agent($platform, instruction: 'You are a helpful assistant.', model: 'gpt-4');
        $agent->call('Hello');

        $this->assertInstanceOf(MessageBag::class, $captured);
        $this->assertNotNull($captured->getSystemMessage());
        $this->assertSame('You are a helpful assistant.', $captured->getSystemMessage()->getContent());
    }

    public function testStatefulAgentPersistsConversationAcrossCalls()
    {
        $store = new InMemoryStore();
        $agent = new Agent(new InMemoryPlatform('Hi'), model: 'gpt-4', store: $store);

        $agent->call('Hello');
        $this->assertCount(2, $store->load());

        $agent->call('How are you?');
        $this->assertCount(4, $store->load());
    }

    public function testRunManyReturnsResultsKeyedByInput()
    {
        $agent = new Agent(new InMemoryPlatform('Hi'), model: 'gpt-4');

        $results = $agent->runMany(['first' => 'Hello', 'second' => 'Hey'])->await();

        $this->assertSame(['first', 'second'], array_keys($results));
        $this->assertSame('Hi', $results['first']->getContent());
        $this->assertSame('Hi', $results['second']->getContent());
    }

    public function testConsumingAnExecutionTwiceThrows()
    {
        $agent = new Agent(new InMemoryPlatform('Hi'), model: 'gpt-4');

        $execution = $agent->run('Hello');
        $execution->await();

        $this->expectException(LogicException::class);

        $execution->await();
    }

    public function testStatefulAgentPersistsStructuredOutput()
    {
        $store = new InMemoryStore();
        $agent = new Agent(
            new InMemoryPlatform(static fn (): ObjectResult => new ObjectResult(['verdict' => 'confirmed'])),
            model: 'gpt-4',
            store: $store,
        );

        $agent->call('Check this claim.');

        $messages = $store->load()->getMessages();
        $this->assertCount(2, $messages);
        $this->assertSame('{"verdict":"confirmed"}', $messages[1]->asText());
    }

    public function testToolInteractionIsAnsweredThroughRun()
    {
        $agent = new Agent(
            $this->interactivePlatform($secondCallMessages),
            tools: [new AskUserTool()],
            model: 'gpt-4',
        );

        $result = $agent->run('Start the recap.')
            ->onInteraction(static function (Interaction $interaction): InteractionResponse {
                self::assertSame('Which season should I cover?', $interaction->getPrompt());

                return new InteractionResponse('Season 5');
            })
            ->await();

        $this->assertSame('All done.', $result->getContent());
        $this->assertInstanceOf(MessageBag::class, $secondCallMessages);
        $toolMessages = array_filter($secondCallMessages->getMessages(), static fn (object $message): bool => $message instanceof ToolCallMessage);
        $this->assertSame('Season 5', array_values($toolMessages)[0]->asText());
    }

    public function testToolInteractionThroughCallThrowsWithResumableState()
    {
        $agent = new Agent(
            $this->interactivePlatform($unused),
            tools: [new AskUserTool()],
            model: 'gpt-4',
        );

        try {
            $agent->call('Start the recap.');
            $this->fail('Expected an InteractionRequiredException.');
        } catch (InteractionRequiredException $e) {
            $interaction = $e->getInteraction();
        }

        $this->assertSame('Which season should I cover?', $interaction->getPrompt());
        $this->assertNotNull($interaction->getToolCall());
        $this->assertSame('ask_user', $interaction->getToolCall()->getName());

        // the snapshot holds the full conversation: user message + assistant tool call
        $messages = $interaction->getMessages();
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[0]);

        // resume in a "new process": rebuild the conversation, append the human answer
        $resumed = new MessageBag(...$messages);
        $resumed->add(Message::ofToolCall($interaction->getToolCall(), 'Season 5'));

        $result = $agent->call($resumed);
        $this->assertSame('All done.', $result->getContent());
    }

    /**
     * A platform that requests the ask_user tool on a fresh conversation and
     * finishes once a tool result is present.
     */
    private function interactivePlatform(?MessageBag &$lastMessages): InMemoryPlatform
    {
        return new InMemoryPlatform(static function (Model $model, array|string|object $input) use (&$lastMessages): TextResult|ToolCallResult {
            \assert($input instanceof MessageBag);
            $lastMessages = $input;

            foreach ($input->getMessages() as $message) {
                if ($message instanceof ToolCallMessage) {
                    return new TextResult('All done.');
                }
            }

            return new ToolCallResult([new ToolCall('call_1', 'ask_user', ['question' => 'Which season should I cover?'])]);
        });
    }
}

#[AsTool('ask_user', 'Asks the editorial team a question and returns their answer.')]
final class AskUserTool
{
    public function __invoke(string $question): string
    {
        throw ToolInteractionException::askUser($question);
    }
}
