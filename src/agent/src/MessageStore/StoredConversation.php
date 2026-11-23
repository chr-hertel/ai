<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\MessageStore;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Result\ToolCallState;

/**
 * Continues a stored conversation with the given messages: the stored ones stay the source of truth, except for
 * suspended tool calls the given messages approve or reject.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class StoredConversation
{
    /**
     * The stored conversation with the decisions on its suspended tool calls, followed by the given messages it
     * doesn't contain yet.
     *
     * @throws InvalidArgumentException if a tool call got approved or rejected that is not suspended in the stored conversation
     */
    public static function continue(MessageBag $stored, MessageBag $input): MessageBag
    {
        $storedIds = array_flip(array_map(static fn (MessageInterface $message): string => $message->getId()->toRfc4122(), $stored->getMessages()));

        $messages = $stored;
        foreach ($input->getMessages() as $message) {
            if ($message instanceof AssistantMessage && isset($storedIds[$message->getId()->toRfc4122()])) {
                $messages = self::withDecisions($messages, $message);
            }
        }

        $messages = $messages->merge(new MessageBag(...array_filter(
            $input->getMessages(),
            static fn (MessageInterface $message): bool => !isset($storedIds[$message->getId()->toRfc4122()]),
        )));
        $messages->getMetadata()->set(array_replace($stored->getMetadata()->all(), $input->getMetadata()->all()));

        return $messages;
    }

    private static function withDecisions(MessageBag $stored, AssistantMessage $message): MessageBag
    {
        $storedMessage = null;
        foreach ($stored->getMessages() as $candidate) {
            if ($candidate->getId()->equals($message->getId())) {
                $storedMessage = $candidate;
            }
        }

        if (!$storedMessage instanceof AssistantMessage) {
            return $stored;
        }

        $storedToolCalls = [];
        foreach ($storedMessage->getToolCalls() as $toolCall) {
            $storedToolCalls[$toolCall->getId()] = $toolCall;
        }

        $decided = $storedMessage;
        foreach ($message->getToolCalls() as $toolCall) {
            $storedToolCall = $storedToolCalls[$toolCall->getId()] ?? null;
            $isDecision = ToolCallState::Approved === $toolCall->getState() || ToolCallState::Rejected === $toolCall->getState();

            if (null === $storedToolCall || $storedToolCall->getState() === $toolCall->getState() || !$isDecision) {
                continue;
            }

            if (!$storedToolCall->isSuspended() || $storedToolCall->getName() !== $toolCall->getName()) {
                throw new InvalidArgumentException(\sprintf('The tool call "%s" with id "%s" can only be approved or rejected while it is suspended.', $toolCall->getName(), $toolCall->getId()));
            }

            $decided = $decided->withToolCall($toolCall);
        }

        return $decided === $storedMessage ? $stored : $stored->replace($storedMessage->getId(), $decided);
    }
}
