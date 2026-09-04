<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Speech\SpeechConfiguration;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\StreamResult;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechAgent implements AgentInterface
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly SpeechConfiguration $configuration,
        private readonly ?PlatformInterface $speechToTextPlatform = null,
        private readonly ?PlatformInterface $textToSpeechPlatform = null,
    ) {
    }

    /**
     * Starts the agent and returns a lazy {@see Execution} reporting the transcription, the inner agent's own
     * updates and the speech synthesis as they happen.
     *
     * With text-to-speech streaming enabled, the audio arrives as {@see BinaryDelta} chunks readable through
     * `->getContent()`, and `->cancel()` aborts the synthesis mid-stream, e.g. when the user starts speaking
     * again (barge-in).
     *
     * @param array<string, mixed> $options
     */
    public function call(string|MessageBag|UserMessage $input, array $options = []): Execution
    {
        $cancellation = new Cancellation();
        $streamed = $this->configuration->shouldStreamTextToSpeech() && $this->textToSpeechPlatform instanceof PlatformInterface;

        return new Execution(function () use ($input, $options, $cancellation): \Generator {
            $messages = InputNormalizer::toMessageBag($input);

            if ($this->configuration->supportsSpeechToText() && $this->speechToTextPlatform instanceof PlatformInterface) {
                $messages = yield from $this->transcribe($messages, $options, $cancellation);
            }

            $result = null;
            foreach ($cancellation->forward($this->agent->call($messages, $options)) as $update) {
                if ($update instanceof ResultUpdate) {
                    $result = $update->getResult();

                    continue;
                }

                if ($update instanceof Progress) {
                    yield $update;
                }
            }

            if ($cancellation->isRequested()) {
                return;
            }

            if (!$result instanceof ResultInterface) {
                throw new RuntimeException(\sprintf('The agent "%s" finished without producing a result.', $this->agent->getName()));
            }

            if (!$this->textToSpeechPlatform instanceof PlatformInterface || !$this->configuration->supportsTextToSpeech()) {
                yield new ResultUpdate($result);

                return;
            }

            $text = $result->getContent();
            $ttsOptions = $this->configuration->getTextToSpeechOptions();

            if ($this->configuration->shouldStreamTextToSpeech()) {
                $ttsOptions['stream'] = true;
            }

            yield new Progress('speech_synthesis', 'Synthesizing speech.', $text);

            $speechResult = $this->textToSpeechPlatform->invoke(
                $this->configuration->getTextToSpeechModel(),
                $text,
                $ttsOptions,
            );
            $cancellation->activate($speechResult->getRawResult());

            $speechResult->getMetadata()->add('text', $text);

            $speech = $speechResult->getResult();

            // a bridge without streaming support answers with the buffered audio, even with the option set
            if (!$speech instanceof StreamResult) {
                yield new ResultUpdate($speech);

                return;
            }

            yield from $this->synthesize($speech, $cancellation);
        }, streamed: $streamed, cancellation: $cancellation);
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }

    /**
     * Transcribes the audio of the latest user message, reporting the transcript as a progress update.
     *
     * @param array<string, mixed> $options
     *
     * @return \Generator<int, Progress, mixed, MessageBag>
     */
    private function transcribe(MessageBag $messages, array $options, Cancellation $cancellation): \Generator
    {
        try {
            $latestUserMessage = $messages->latestAs(Role::User);
        } catch (InvalidArgumentException) {
            return $messages;
        }

        if (!$latestUserMessage instanceof UserMessage) {
            return $messages;
        }

        if (!$latestUserMessage->hasAudioContent()) {
            return $messages;
        }

        $audio = $latestUserMessage->getAudioContent();

        $result = $this->speechToTextPlatform->invoke(
            $this->configuration->getSpeechToTextModel(),
            $audio,
            [
                ...$this->configuration->getSpeechToTextOptions(),
                ...$options,
            ],
        );
        $cancellation->activate($result->getRawResult());

        $text = new Text($result->asText());
        $messages->replace($latestUserMessage->getId(), Message::ofUser($text));

        yield new Progress('transcription', 'Transcribed the audio input.', $text->getText());

        return $messages;
    }

    /**
     * Forwards the streamed audio chunks as progress updates and returns them as one buffered result.
     *
     * @return \Generator<int, Progress|ResultUpdate, mixed, void>
     */
    private function synthesize(StreamResult $stream, Cancellation $cancellation): \Generator
    {
        $audio = '';
        $mimeType = null;

        foreach ($stream->getContent() as $delta) {
            if (!$delta instanceof BinaryDelta) {
                continue;
            }

            $audio .= $delta->getData();
            $mimeType ??= $delta->getMimeType();

            yield new Progress('delta', 'Received a streamed delta.', $delta);

            if ($cancellation->isRequested()) {
                return;
            }
        }

        if ($cancellation->isRequested()) {
            return;
        }

        $speech = new BinaryResult($audio, $mimeType);
        $speech->getMetadata()->merge($stream->getMetadata());

        yield new ResultUpdate($speech);
    }
}
