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

use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Dispatched by the agent before it executes the tool calls of a model round.
 *
 * Suspended tool calls are not executed: the execution ends waiting for input, and the next call of the
 * agent resolves them with {@see \Symfony\AI\Agent\Execution\ToolCallResolutions}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallsRequested
{
    /**
     * @var array<string, string|null> reasons of the suspended tool calls, keyed by tool call id
     */
    private array $suspensions = [];

    /**
     * @param list<ToolCall>      $toolCalls
     * @param array<string, Tool> $definitions the registered tools, keyed by name
     * @param MessageBag          $messages    the conversation the model answered with the tool calls
     */
    public function __construct(
        private readonly array $toolCalls,
        private readonly array $definitions,
        private readonly MessageBag $messages,
    ) {
    }

    /**
     * @return list<ToolCall>
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

    public function suspend(ToolCall $toolCall, ?string $reason = null): void
    {
        $this->suspensions[$toolCall->getId()] = $reason;
    }

    public function isSuspended(ToolCall $toolCall): bool
    {
        return \array_key_exists($toolCall->getId(), $this->suspensions);
    }

    /**
     * @return list<ToolCall>
     */
    public function getSuspendedToolCalls(): array
    {
        return array_values(array_filter($this->toolCalls, $this->isSuspended(...)));
    }

    /**
     * @return array<string, string|null> keyed by tool call id
     */
    public function getSuspensionReasons(): array
    {
        return $this->suspensions;
    }
}
