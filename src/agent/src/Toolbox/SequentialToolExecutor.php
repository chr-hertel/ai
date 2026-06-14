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
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * Executes the requested tool calls one after another.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SequentialToolExecutor implements ToolExecutorInterface
{
    public function __construct(
        private readonly ToolboxInterface $toolbox,
        private readonly ToolResultConverter $resultConverter = new ToolResultConverter(),
    ) {
    }

    public function execute(ToolCallResult $result, AgentContext $context): \Generator
    {
        $toolCalls = $result->getContent();
        $messages = [Message::ofAssistant(...$toolCalls)];

        foreach ($toolCalls as $toolCall) {
            yield new Progress('tool_call', \sprintf('Executing tool "%s".', $toolCall->getName()), $toolCall);

            $toolResult = $this->toolbox->execute($toolCall);
            $messages[] = Message::ofToolCall($toolCall, $this->resultConverter->convert($toolResult));
        }

        return $messages;
    }
}
