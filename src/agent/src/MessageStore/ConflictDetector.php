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

use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Result\ToolCallState;

/**
 * Detects messages that were saved in the meantime, using the id of the last stored message as version.
 *
 * Approving or rejecting a suspended tool call changes a stored message in place, so the version also covers the
 * state of the tool calls, and a tool call decided in the meantime conflicts.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ConflictDetector
{
    /**
     * @throws ConflictException if the messages don't start with the stored ones, or change the state of a stored
     *                           tool call other than by approving or rejecting a suspended one
     */
    public static function assertContinues(MessageBag $stored, MessageBag $messages): void
    {
        $storedIds = array_map(self::id(...), $stored->getMessages());
        $ids = array_map(self::id(...), \array_slice($messages->getMessages(), 0, \count($storedIds)));

        if ($storedIds !== $ids) {
            throw ConflictException::forMessages($messages);
        }

        $states = self::toolCallStates($messages, true);
        foreach (self::toolCallStates($stored, true) as $id => $storedState) {
            $state = $states[$id] ?? $storedState;
            $isDecision = ToolCallState::Suspended === $storedState && (ToolCallState::Approved === $state || ToolCallState::Rejected === $state);

            if ($state !== $storedState && !$isDecision) {
                throw ConflictException::forMessages($messages);
            }
        }
    }

    /**
     * @return string the id of the last message, or an empty string without messages; a hash of 36 characters once
     *                tool calls of the messages carry a state
     */
    public static function version(MessageBag $messages): string
    {
        $all = $messages->getMessages();
        if ([] === $all) {
            return '';
        }

        $version = self::id($all[array_key_last($all)]);

        $states = self::toolCallStates($messages);
        if ([] === $states) {
            return $version;
        }

        $fingerprint = $version;
        foreach ($states as $id => $state) {
            $fingerprint .= '|'.$id.'='.$state->value;
        }

        return substr(hash('sha256', $fingerprint), 0, 36);
    }

    /**
     * @return array<string, ToolCallState> keyed by tool call id, only the ones that are not just requested by default
     */
    private static function toolCallStates(MessageBag $messages, bool $withRequested = false): array
    {
        $states = [];
        foreach ($messages->getMessages() as $message) {
            if (!$message instanceof AssistantMessage) {
                continue;
            }

            foreach ($message->getToolCalls() as $toolCall) {
                if ($withRequested || ToolCallState::Requested !== $toolCall->getState()) {
                    $states[$toolCall->getId()] = $toolCall->getState();
                }
            }
        }

        return $states;
    }

    private static function id(MessageInterface $message): string
    {
        return $message->getId()->toRfc4122();
    }
}
