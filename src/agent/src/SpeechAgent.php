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

use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Speech\SpeechConfiguration;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;

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

    public function call(string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): ResultInterface
    {
        $messages = $this->normalizeInput($input);

        if ($this->configuration->supportsSpeechToText() && $this->speechToTextPlatform instanceof PlatformInterface) {
            $messages = $this->transcribe($messages, $options);
        }

        $result = $this->agent->call($messages, $context, $options);

        if (!$this->textToSpeechPlatform instanceof PlatformInterface) {
            return $result;
        }

        if (!$this->configuration->supportsTextToSpeech()) {
            return $result;
        }

        $speechResult = $this->textToSpeechPlatform->invoke(
            $this->configuration->getTextToSpeechModel(),
            $result->getContent(),
            $this->configuration->getTextToSpeechOptions(),
        );

        $speechResult->getMetadata()->add('text', $result->getContent());

        return $speechResult->getResult();
    }

    public function run(string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): Execution
    {
        return new Execution(function () use ($input, $context, $options): \Generator {
            yield new ResultUpdate($this->call($input, $context, $options));
        });
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }

    private function normalizeInput(string|MessageBag|UserMessage $input): MessageBag
    {
        if ($input instanceof MessageBag) {
            return $input;
        }

        if ($input instanceof UserMessage) {
            return new MessageBag($input);
        }

        return new MessageBag(Message::ofUser($input));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function transcribe(MessageBag $messages, array $options): MessageBag
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

        $text = new Text($result->asText());
        $messages->replace($latestUserMessage->getId(), Message::ofUser($text));

        return $messages;
    }
}
