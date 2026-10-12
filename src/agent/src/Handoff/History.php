<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Handoff;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Defines which part of the conversation the target of a {@see Handoff} receives.
 *
 * The delegating agent's system message and tool calls never reach the target, and assistant messages are reduced
 * to their text, since thinking blocks are bound to the model that produced them.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class History
{
    private function __construct(
        private readonly ?int $limit,
        private readonly bool $lastMessageOnly = false,
    ) {
    }

    /**
     * Only the latest user message.
     */
    public static function lastMessage(): self
    {
        return new self(null, true);
    }

    /**
     * The whole conversation.
     */
    public static function conversation(): self
    {
        return new self(null);
    }

    /**
     * The given number of most recent messages.
     */
    public static function window(int $messages): self
    {
        if ($messages < 1) {
            throw new InvalidArgumentException('A history window must contain at least one message.');
        }

        return new self($messages);
    }

    public function apply(MessageBag $messages): MessageBag
    {
        $conversation = $messages->withoutSystemMessage()->withoutToolMessages()->getMessages();

        if ($this->lastMessageOnly) {
            $userMessages = array_filter($conversation, static fn (MessageInterface $message): bool => $message instanceof UserMessage);
            $conversation = \array_slice($userMessages, -1);
        } elseif (null !== $this->limit) {
            $conversation = \array_slice($conversation, -$this->limit);
        }

        return new MessageBag(...array_map(
            static fn (MessageInterface $message): MessageInterface => $message instanceof AssistantMessage ? Message::ofAssistant($message->asText() ?? '') : $message,
            $conversation,
        ));
    }
}
