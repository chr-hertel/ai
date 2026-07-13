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

use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Execution\InteractionReason;
use Symfony\AI\Agent\Execution\InteractionResponse;
use Symfony\AI\Agent\Execution\Update\Interaction;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Toolbox\Exception\ToolInteractionException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * Executes the requested tool calls one after another.
 *
 * Two situations pause the execution with an {@see Interaction} update:
 * a tool listed in $toolsRequiringApproval is about to be called, or a tool
 * throws a {@see ToolInteractionException}. In both cases the
 * {@see InteractionResponse} sent back by the consumer decides how the
 * execution continues.
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

    public function execute(ToolCallResult $result, AgentContext $context): \Generator
    {
        $toolCalls = $result->getContent();
        $messages = [Message::ofAssistant(...$toolCalls)];

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
                    $messages[] = Message::ofToolCall($toolCall, 'The tool call was denied by the user.');

                    continue;
                }
            }

            yield new Progress('tool_call', \sprintf('Executing tool "%s".', $toolCall->getName()), $toolCall);

            try {
                $toolResult = $this->toolbox->execute($toolCall);
            } catch (ToolInteractionException $interaction) {
                $response = yield new Interaction(
                    $interaction->getReason(),
                    $interaction->getPrompt(),
                    $interaction->getSchema(),
                    $toolCall,
                    $messages,
                );

                $messages[] = Message::ofToolCall($toolCall, $this->convertResponse($response));

                continue;
            }

            $messages[] = Message::ofToolCall($toolCall, $this->resultConverter->convert($toolResult));
        }

        return $messages;
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
