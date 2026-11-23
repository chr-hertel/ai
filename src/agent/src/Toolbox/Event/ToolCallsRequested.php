<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Event;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallState;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Dispatched by the agent before it executes the tool calls of a model round.
 *
 * Requested tool calls get executed. A suspended call ends the execution waiting for input, until the call gets
 * approved or rejected in the conversation that is handed to the next agent call. A rejected call is answered
 * with its reason instead of being executed.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallsRequested
{
    /**
     * @var list<ToolCall>
     */
    private array $toolCalls;

    /**
     * @param list<ToolCall>      $toolCalls
     * @param array<string, Tool> $definitions the registered tools, keyed by name
     * @param MessageBag          $messages    the conversation the model answered with the tool calls
     */
    public function __construct(
        array $toolCalls,
        private readonly array $definitions,
        private readonly MessageBag $messages,
    ) {
        $this->toolCalls = $toolCalls;
    }

    /**
     * @return list<ToolCall> the tool calls of the round, in their current state
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * The definition of the called tool, null for a tool that is not registered.
     */
    public function getDefinition(ToolCall $toolCall): ?Tool
    {
        return $this->definitions[$toolCall->getName()] ?? null;
    }

    public function getMessages(): MessageBag
    {
        return $this->messages;
    }

    /**
     * Holds the tool call back until it gets approved or rejected, a rejected call stays rejected.
     */
    public function suspend(ToolCall $toolCall, ?string $reason = null): void
    {
        $this->transition($toolCall, static fn (ToolCall $current): ToolCall => ToolCallState::Requested === $current->getState() ? $current->suspend($reason) : $current);
    }

    /**
     * Answers the tool call with the reason instead of executing it.
     */
    public function reject(ToolCall $toolCall, ?string $reason = null): void
    {
        $this->transition($toolCall, static fn (ToolCall $current): ToolCall => ToolCallState::Rejected === $current->getState() ? $current : $current->reject($reason));
    }

    /**
     * @param \Closure(ToolCall): ToolCall $transition
     */
    private function transition(ToolCall $toolCall, \Closure $transition): void
    {
        foreach ($this->toolCalls as $i => $current) {
            if ($current->getId() === $toolCall->getId()) {
                $this->toolCalls[$i] = $transition($current);

                return;
            }
        }

        throw new InvalidArgumentException(\sprintf('The round has no tool call with id "%s".', $toolCall->getId()));
    }
}
