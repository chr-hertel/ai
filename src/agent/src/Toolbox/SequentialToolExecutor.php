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

    public function execute(ToolCallResult $toolCallResult): ToolExecution
    {
        $toolCalls = $toolCallResult->getContent();

        $messages = [Message::ofAssistant(...$toolCalls)];
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $results[] = $result = $this->toolbox->execute($toolCall);
            $messages[] = Message::ofToolCall($toolCall, $this->resultConverter->convert($result));
        }

        return new ToolExecution($messages, $results);
    }
}
