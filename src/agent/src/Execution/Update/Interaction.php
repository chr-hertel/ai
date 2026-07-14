<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution\Update;

use Symfony\AI\Agent\Execution\InteractionReason;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Agent\Execution\UpdateType;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * A blocking update: the execution pauses until a human sends a response back.
 *
 * When the interaction originates from a tool call, it carries the pending
 * tool call and a snapshot of the conversation so far. This is the complete
 * state needed to persist the paused execution and resume it later — even in
 * another process: reconstruct a MessageBag from getMessages(), append a tool
 * call message with the human's answer for getToolCall(), and call the agent
 * again with the rebuilt conversation.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Interaction implements UpdateInterface
{
    /**
     * @param array<string, mixed>   $schema   optional schema or list of choices describing the expected response
     * @param ToolCall|null          $toolCall the tool call awaiting the response, if the interaction was raised by a tool
     * @param list<MessageInterface> $messages snapshot of the conversation up to the pause, including the assistant
     *                                         tool call message and already completed tool results
     */
    public function __construct(
        private readonly InteractionReason $reason,
        private readonly string $prompt,
        private readonly array $schema = [],
        private readonly ?ToolCall $toolCall = null,
        private readonly array $messages = [],
    ) {
    }

    /**
     * @param list<MessageInterface> $messages
     */
    public function withMessages(array $messages): self
    {
        return new self($this->reason, $this->prompt, $this->schema, $this->toolCall, $messages);
    }

    public function getType(): UpdateType
    {
        return UpdateType::Interaction;
    }

    public function getReason(): InteractionReason
    {
        return $this->reason;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    public function getToolCall(): ?ToolCall
    {
        return $this->toolCall;
    }

    /**
     * @return list<MessageInterface>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
