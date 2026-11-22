<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution;

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallState;

/**
 * Reads the tool calling state of a conversation.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class Conversation
{
    /**
     * The unanswered tool calls of the last assistant message, as long as only tool messages follow it.
     *
     * @return list<ToolCall>
     */
    public static function pendingToolCalls(MessageBag $messages): array
    {
        $answered = [];
        foreach (array_reverse($messages->getMessages()) as $message) {
            if ($message instanceof ToolCallMessage) {
                $answered[$message->getToolCall()->getId()] = true;

                continue;
            }

            if (!$message instanceof AssistantMessage) {
                return [];
            }

            return array_values(array_filter($message->getToolCalls(), static fn (ToolCall $toolCall): bool => !isset($answered[$toolCall->getId()])));
        }

        return [];
    }

    /**
     * The tool calling rounds since the last user message.
     */
    public static function toolRoundsOfTurn(MessageBag $messages): int
    {
        $rounds = 0;
        foreach (array_reverse($messages->getMessages()) as $message) {
            if ($message instanceof UserMessage) {
                break;
            }

            if ($message instanceof AssistantMessage && $message->hasToolCalls()) {
                ++$rounds;
            }
        }

        return $rounds;
    }

    /**
     * Answers the tool calls left unanswered once the conversation moved on, since providers reject a conversation
     * with an unanswered tool call before a later message.
     */
    public static function answerAbandonedToolCalls(MessageBag $messages): MessageBag
    {
        $list = $messages->getMessages();
        $answered = [];
        $result = [];
        $abandoned = false;

        foreach ($list as $i => $message) {
            $result[] = $message;

            if ($message instanceof ToolCallMessage) {
                $answered[$message->getToolCall()->getId()] = true;
            }

            $next = $list[$i + 1] ?? null;
            if (null === $next || $next instanceof ToolCallMessage) {
                continue;
            }

            // the next message ends the answers of the last assistant message with tool calls
            $assistant = self::lastToolCallingMessage($result);
            if (null === $assistant) {
                continue;
            }

            foreach ($assistant->getToolCalls() as $toolCall) {
                if (!isset($answered[$toolCall->getId()])) {
                    $result[] = Message::ofToolCall($toolCall, self::abandonedAnswer($toolCall));
                    $answered[$toolCall->getId()] = true;
                    $abandoned = true;
                }
            }
        }

        if (!$abandoned) {
            return $messages;
        }

        return (new MessageBag(...$result))->withId($messages->getId());
    }

    private static function abandonedAnswer(ToolCall $toolCall): string
    {
        return match ($toolCall->getState()) {
            ToolCallState::Suspended => 'The tool call was suspended and neither approved nor rejected.',
            ToolCallState::Approved => 'The tool call was approved, but not executed.',
            ToolCallState::Rejected => $toolCall->getReason() ?? 'The tool call was rejected.',
            ToolCallState::Requested => 'The tool call was not answered.',
        };
    }

    /**
     * @param list<MessageInterface> $messages
     */
    private static function lastToolCallingMessage(array $messages): ?AssistantMessage
    {
        foreach (array_reverse($messages) as $message) {
            if ($message instanceof ToolCallMessage) {
                continue;
            }

            if ($message instanceof AssistantMessage && $message->hasToolCalls()) {
                return $message;
            }

            return null;
        }

        return null;
    }
}
