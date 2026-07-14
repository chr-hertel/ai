<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Symfony\AI\Agent\Execution\InteractionReason;
use Symfony\AI\Agent\Execution\InteractionResponse;
use Symfony\AI\Agent\Execution\Update\Interaction;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Agent\Toolbox\Exception\ToolInteractionException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Executes the requested tool calls one after another.
 *
 * Two situations pause the execution with an {@see Interaction} update: a tool listed in
 * $toolsRequiringApproval is about to be called, or a tool throws a {@see ToolInteractionException}. In both
 * cases the {@see InteractionResponse} the consumer sends back decides how the execution continues.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SequentialToolExecutor implements ToolExecutorInterface
{
    /**
     * @param list<string> $toolsRequiringApproval names of tools that pause for human approval before executing
     */
    public function __construct(
        private readonly ToolboxInterface $toolbox,
        private readonly ToolResultConverter $resultConverter = new ToolResultConverter(),
        private readonly array $toolsRequiringApproval = [],
    ) {
    }

    /**
     * @param ToolCall[] $toolCalls
     *
     * @return \Generator<int, UpdateInterface, mixed, ToolResult[]>
     */
    public function execute(array $toolCalls): \Generator
    {
        $results = [];
        // the tool messages of this round, carried by an interaction so a paused execution can be resumed
        $messages = [];

        foreach ($toolCalls as $toolCall) {
            if (\in_array($toolCall->getName(), $this->toolsRequiringApproval, true)) {
                $response = yield new Interaction(
                    InteractionReason::ToolApproval,
                    \sprintf('The agent wants to call the tool "%s".', $toolCall->getName()),
                    ['arguments' => $toolCall->getArguments()],
                    $toolCall,
                    $messages,
                );

                if (!$response instanceof InteractionResponse || !$response->isApproved()) {
                    $results[] = $denied = new ToolResult($toolCall, 'The tool call was denied by the user.');
                    $messages[] = Message::ofToolCall($toolCall, $this->resultConverter->convert($denied));

                    continue;
                }
            }

            yield new Progress('tool_call', \sprintf('Executing tool "%s".', $toolCall->getName()), $toolCall);

            try {
                $result = $this->toolbox->execute($toolCall);
            } catch (ToolInteractionException $interaction) {
                $response = yield new Interaction(
                    $interaction->getReason(),
                    $interaction->getPrompt(),
                    $interaction->getSchema(),
                    $toolCall,
                    $messages,
                );

                $result = new ToolResult($toolCall, $this->convertResponse($response));
            }

            $results[] = $result;
            $messages[] = Message::ofToolCall($toolCall, $this->resultConverter->convert($result));
        }

        return $results;
    }

    private function convertResponse(mixed $response): string
    {
        $value = $response instanceof InteractionResponse ? $response->getValue() : null;

        if (null === $value) {
            return 'The user did not provide a response.';
        }

        if (\is_string($value)) {
            return $value;
        }

        if (\is_scalar($value)) {
            return var_export($value, true);
        }

        return json_encode($value, \JSON_THROW_ON_ERROR);
    }
}
