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
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
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
}
