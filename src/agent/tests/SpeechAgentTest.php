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
use Symfony\AI\Agent\Exception\RuntimeException as AgentRuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Speech\SpeechConfiguration;
use Symfony\AI\Agent\SpeechAgent;
use Symfony\AI\Agent\Tests\Fixtures\SuspendingConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SpeechAgentTest extends TestCase
{
    public function testCallDelegatesToInnerAgent()
    {
        $expectedResult = new TextResult('hello');

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution($expectedResult));

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $agent = new SpeechAgent($innerAgent, new SpeechConfiguration(), $platform, $platform);

        $result = $agent->call(new MessageBag(Message::ofUser('Hello')))->getResult();

        $this->assertSame($expectedResult, $result);
    }

    public function testCallTranscribesAudioInput()
    {
        $sttResult = new DeferredResult(new PlainConverter(new TextResult('transcribed text')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn($sttResult);

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->with($this->callback(static function (MessageBag $messages): bool {
                $latestUser = $messages->latestAs(Role::User);

                return [new Text('transcribed text')] == $latestUser->getContent();
            }))
            ->willReturn($this->execution(new TextResult('response')));

        $configuration = new SpeechConfiguration(sttModel: 'whisper-1');

        $messageBag = new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__).'/../../fixtures/audio.mp3')),
        );

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);
        $result = $agent->call($messageBag);

        $this->assertSame('response', $result->getContent());
    }

    public function testCallSkipsTranscriptionWhenNoAudio()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('response')));

        $configuration = new SpeechConfiguration(sttModel: 'whisper-1');

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);
        $result = $agent->call(new MessageBag(Message::ofUser('Hello text')));

        $this->assertSame('response', $result->getContent());
    }

    public function testCallSkipsTranscriptionWhenNoUserMessage()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('response')));

        $configuration = new SpeechConfiguration(sttModel: 'whisper-1');

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);
        $result = $agent->call(new MessageBag());

        $this->assertSame('response', $result->getContent());
    }

    public function testCallAttachesSpeechMetadataWhenTtsConfigured()
    {
        $ttsResult = new DeferredResult(new PlainConverter(new BinaryResult('audio-binary')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn($ttsResult);

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('hello')));

        $configuration = new SpeechConfiguration(ttsModel: 'eleven_multilingual_v2');

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);
        $result = $agent->call(new MessageBag(Message::ofUser('Say hello')))->getResult();

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio-binary', $result->getContent());
        $this->assertSame('hello', $result->getMetadata()->get('text'));
    }

    public function testCallReturnsPlainResultWhenTtsNotConfigured()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('hello')));

        $agent = new SpeechAgent($innerAgent, new SpeechConfiguration(), $platform, $platform);
        $result = $agent->call(new MessageBag(Message::ofUser('Say hello')))->getResult();

        $this->assertInstanceOf(TextResult::class, $result);
    }

    public function testCallHandlesBothSttAndTts()
    {
        $sttResult = new DeferredResult(new PlainConverter(new TextResult('transcribed text')), new InMemoryRawResult());
        $ttsResult = new DeferredResult(new PlainConverter(new BinaryResult('audio-binary')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))
            ->method('invoke')
            ->willReturnOnConsecutiveCalls($sttResult, $ttsResult);

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('LLM response')));

        $configuration = new SpeechConfiguration(
            ttsModel: 'eleven_multilingual_v2',
            sttModel: 'whisper-1',
        );

        $messageBag = new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__).'/../../fixtures/audio.mp3')),
        );

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);
        $result = $agent->call($messageBag)->getResult();

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio-binary', $result->getContent());
        $this->assertSame('LLM response', $result->getMetadata()->get('text'));
    }

    public function testExceptionIsThrownWhenTtsFails()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->willThrowException(new RuntimeException('TTS service unavailable.'));

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('hello')));

        $configuration = new SpeechConfiguration(ttsModel: 'eleven_multilingual_v2');

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TTS service unavailable.');
        $this->expectExceptionCode(0);
        $agent->call(new MessageBag(Message::ofUser('Say hello')))->getResult();
    }

    public function testCallWithMultipleMessagesWorksCorrectly()
    {
        $sttResult = new DeferredResult(new PlainConverter(new TextResult('what is the weather?')), new InMemoryRawResult());
        $ttsResult = new DeferredResult(new PlainConverter(new BinaryResult('audio-response')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))
            ->method('invoke')
            ->willReturnOnConsecutiveCalls($sttResult, $ttsResult);

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->with($this->callback(static function (MessageBag $messages): bool {
                // Should have 3 messages: old user text, assistant, new transcribed text
                if (3 !== \count($messages)) {
                    return false;
                }

                $latestUser = $messages->latestAs(Role::User);

                return [new Text('what is the weather?')] == $latestUser->getContent();
            }))
            ->willReturn($this->execution(new TextResult('It is sunny')));

        $configuration = new SpeechConfiguration(
            ttsModel: 'tts-1',
            sttModel: 'whisper-1',
        );

        $messageBag = new MessageBag(
            Message::ofUser('Hello'),
            Message::ofAssistant('Hi there!'),
            Message::ofUser(Audio::fromFile(\dirname(__DIR__).'/../../fixtures/audio.mp3')),
        );

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);
        $result = $agent->call($messageBag)->getResult();

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio-response', $result->getContent());
        $this->assertSame('It is sunny', $result->getMetadata()->get('text'));
    }

    public function testGetNameDelegatesToInnerAgent()
    {
        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('getName')
            ->willReturn('my-agent');

        $platform = $this->createMock(PlatformInterface::class);

        $agent = new SpeechAgent($innerAgent, new SpeechConfiguration(), $platform, $platform);

        $this->assertSame('my-agent', $agent->getName());
    }

    public function testCancelPropagatesToTheDelegatedAgentExecution()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('cancel');

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn(new DeferredResult(new SuspendingConverter(), new RawHttpResult($response)));

        $agent = new SpeechAgent(new Agent($platform, 'gpt-4'), new SpeechConfiguration());
        $execution = $agent->call(new MessageBag(Message::ofUser('Hello')));

        $fiber = new \Fiber(static fn (): ResultInterface => $execution->getResult());
        $fiber->start();

        $execution->cancel();

        $this->expectException(AgentRuntimeException::class);
        $this->expectExceptionMessage('The agent execution was canceled.');

        $fiber->resume();
    }

    public function testCallForwardsProgressUpdatesFromInnerAgent()
    {
        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn(new Execution(static function (): \Generator {
                yield new Progress('model_request', 'Invoking model.');
                yield new Progress('delta', 'Received a streamed delta.');
                yield new ResultUpdate(new TextResult('hello'));
            }));

        $agent = new SpeechAgent($innerAgent, new SpeechConfiguration());

        $updates = iterator_to_array($agent->call(new MessageBag(Message::ofUser('Hello'))));

        $this->assertCount(3, $updates);
        $this->assertInstanceOf(Progress::class, $updates[0]);
        $this->assertSame('model_request', $updates[0]->getStage());
        $this->assertInstanceOf(Progress::class, $updates[1]);
        $this->assertSame('delta', $updates[1]->getStage());
        $this->assertInstanceOf(ResultUpdate::class, $updates[2]);
        $this->assertSame('hello', $updates[2]->getResult()->getContent());
    }

    public function testCallStreamsTheSynthesizedSpeech()
    {
        $ttsResult = new DeferredResult(new PlainConverter($this->stream('foo', 'bar')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with('eleven_multilingual_v2', 'hello', ['stream' => true])
            ->willReturn($ttsResult);

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('hello')));

        $configuration = new SpeechConfiguration(ttsModel: 'eleven_multilingual_v2', ttsStream: true);

        $agent = new SpeechAgent($innerAgent, $configuration, textToSpeechPlatform: $platform);
        $updates = iterator_to_array($agent->call(new MessageBag(Message::ofUser('Say hello'))));

        $this->assertCount(4, $updates);

        $this->assertInstanceOf(Progress::class, $updates[0]);
        $this->assertSame('speech_synthesis', $updates[0]->getStage());
        $this->assertSame('hello', $updates[0]->getPayload());

        $this->assertInstanceOf(Progress::class, $updates[1]);
        $this->assertSame('delta', $updates[1]->getStage());
        $this->assertInstanceOf(BinaryDelta::class, $updates[1]->getPayload());
        $this->assertSame('foo', $updates[1]->getPayload()->getData());

        $this->assertInstanceOf(Progress::class, $updates[2]);
        $this->assertSame('delta', $updates[2]->getStage());

        $this->assertInstanceOf(ResultUpdate::class, $updates[3]);
        $speech = $updates[3]->getResult();
        $this->assertInstanceOf(BinaryResult::class, $speech);
        $this->assertSame('foobar', $speech->getContent());
        $this->assertSame('audio/mpeg', $speech->getMimeType());
        $this->assertSame('hello', $speech->getMetadata()->get('text'));
    }

    public function testStreamedSpeechIsReadableAsStream()
    {
        $ttsResult = new DeferredResult(new PlainConverter($this->stream('foo', 'bar')), new InMemoryRawResult());

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($ttsResult);

        $innerAgent = $this->createStub(AgentInterface::class);
        $innerAgent->method('call')->willReturn($this->execution(new TextResult('hello')));

        $configuration = new SpeechConfiguration(ttsModel: 'eleven_multilingual_v2', ttsStream: true);

        $agent = new SpeechAgent($innerAgent, $configuration, textToSpeechPlatform: $platform);
        $execution = $agent->call(new MessageBag(Message::ofUser('Say hello')));

        $audio = '';
        foreach ($execution->getContent() as $delta) {
            $this->assertInstanceOf(BinaryDelta::class, $delta);

            $audio .= $delta->getData();
        }

        $this->assertSame('foobar', $audio);
    }

    public function testCancelStopsTheSpeechSynthesis()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('cancel');

        $ttsResult = new DeferredResult(new PlainConverter($this->stream('foo', 'bar')), new RawHttpResult($response));

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($ttsResult);

        $innerAgent = $this->createStub(AgentInterface::class);
        $innerAgent->method('call')->willReturn($this->execution(new TextResult('hello')));

        $configuration = new SpeechConfiguration(ttsModel: 'eleven_multilingual_v2', ttsStream: true);

        $agent = new SpeechAgent($innerAgent, $configuration, textToSpeechPlatform: $platform);
        $execution = $agent->call(new MessageBag(Message::ofUser('Say hello')));

        $updates = [];
        foreach ($execution as $update) {
            $updates[] = $update;

            if ($update instanceof Progress && 'delta' === $update->getStage()) {
                $execution->cancel();
            }
        }

        // the synthesis stops on the first chunk, so neither the second delta nor a result is reported
        $this->assertCount(2, $updates);
        $this->assertContainsOnlyInstancesOf(Progress::class, $updates);
    }

    public function testCallReturnsBufferedAudioWhenTheBridgeDoesNotStream()
    {
        $ttsResult = new DeferredResult(new PlainConverter(new BinaryResult('audio-binary')), new InMemoryRawResult());

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($ttsResult);

        $innerAgent = $this->createStub(AgentInterface::class);
        $innerAgent->method('call')->willReturn($this->execution(new TextResult('hello')));

        $configuration = new SpeechConfiguration(ttsModel: 'tts-1', ttsStream: true);

        $agent = new SpeechAgent($innerAgent, $configuration, textToSpeechPlatform: $platform);
        $result = $agent->call(new MessageBag(Message::ofUser('Say hello')))->getResult();

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio-binary', $result->getContent());
        $this->assertSame('hello', $result->getMetadata()->get('text'));
    }

    public function testCallReportsTheTranscriptAsProgress()
    {
        $sttResult = new DeferredResult(new PlainConverter(new TextResult('transcribed text')), new InMemoryRawResult());

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($sttResult);

        $innerAgent = $this->createStub(AgentInterface::class);
        $innerAgent->method('call')->willReturn($this->execution(new TextResult('response')));

        $configuration = new SpeechConfiguration(sttModel: 'whisper-1');

        $messageBag = new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__).'/../../fixtures/audio.mp3')),
        );

        $agent = new SpeechAgent($innerAgent, $configuration, $platform);
        $updates = iterator_to_array($agent->call($messageBag));

        $this->assertCount(2, $updates);
        $this->assertInstanceOf(Progress::class, $updates[0]);
        $this->assertSame('transcription', $updates[0]->getStage());
        $this->assertSame('transcribed text', $updates[0]->getPayload());
        $this->assertInstanceOf(ResultUpdate::class, $updates[1]);
    }

    private function stream(string ...$chunks): StreamResult
    {
        return new StreamResult((static function () use ($chunks): \Generator {
            foreach ($chunks as $chunk) {
                yield new BinaryDelta($chunk, 'audio/mpeg');
            }
        })());
    }

    private function execution(ResultInterface $result): Execution
    {
        return new Execution(static function () use ($result): \Generator {
            yield new ResultUpdate($result);
        });
    }
}
