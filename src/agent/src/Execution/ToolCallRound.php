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

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallState;

/**
 * Handles the tool calls of a round by their state: executes the requested and approved ones, answers the rejected
 * ones with their reason and leaves the suspended ones unanswered.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class ToolCallRound
{
    public function __construct(
        private readonly ?ToolExecutorInterface $toolExecutor,
        private readonly ToolResultConverter $resultConverter = new ToolResultConverter(),
    ) {
    }

    /**
     * @param list<ToolCall> $toolCalls
     *
     * @return \Generator<int, UpdateInterface, mixed, array{MessageBag, SourceCollection, list<ToolResult>}> with the answers appended
     */
    public function run(MessageBag $messages, SourceCollection $sources, array $toolCalls): \Generator
    {
        $executable = array_values(array_filter($toolCalls, static fn (ToolCall $toolCall): bool => ToolCallState::Requested === $toolCall->getState() || ToolCallState::Approved === $toolCall->getState()));
        $toolResults = yield from $this->execute($executable);

        // answered in the order the model requested them
        foreach ($toolCalls as $toolCall) {
            if (ToolCallState::Rejected === $toolCall->getState()) {
                $messages = $messages->with(Message::ofToolCall($toolCall, $toolCall->getReason() ?? 'The tool call was rejected.'));

                continue;
            }

            $toolResult = $toolResults[$toolCall->getId()] ?? null;
            if (null === $toolResult) {
                continue;
            }

            // a tool returning nothing still has to answer its call
            $messages = $messages->with(Message::ofToolCall($toolCall, $this->resultConverter->convert($toolResult) ?? ''));

            if (null !== $toolResult->getSources()) {
                $sources = $sources->merge($toolResult->getSources());
            }
        }

        return [$messages, $sources, array_values($toolResults)];
    }

    /**
     * The assistant message of the round, carrying its tool calls in their state.
     *
     * @param list<ToolCall> $toolCalls
     */
    public static function withToolCalls(AssistantMessage $message, array $toolCalls): AssistantMessage
    {
        foreach ($toolCalls as $toolCall) {
            if (ToolCallState::Requested !== $toolCall->getState()) {
                $message = $message->withToolCall($toolCall);
            }
        }

        return $message;
    }

    /**
     * @param list<ToolCall> $toolCalls
     *
     * @return list<ToolCall>
     */
    public static function inState(array $toolCalls, ToolCallState ...$states): array
    {
        return array_values(array_filter($toolCalls, static fn (ToolCall $toolCall): bool => \in_array($toolCall->getState(), $states, true)));
    }

    /**
     * @param list<ToolCall> $toolCalls
     *
     * @return \Generator<int, UpdateInterface, mixed, array<string, ToolResult>> keyed by tool call id
     */
    private function execute(array $toolCalls): \Generator
    {
        if ([] === $toolCalls) {
            return [];
        }

        if (null === $this->toolExecutor) {
            throw new InvalidArgumentException('Executing a tool call requires a toolbox.');
        }

        $toolResults = yield from $this->toolExecutor->execute($toolCalls);

        $results = [];
        foreach ($toolCalls as $i => $toolCall) {
            $results[$toolCall->getId()] = $toolResults[$i];
        }

        return $results;
    }
}
