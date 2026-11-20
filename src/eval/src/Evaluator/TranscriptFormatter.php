<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Evaluator;

use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Renders a run as plain text transcript for model-graded evaluators.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TranscriptFormatter
{
    public static function format(RecordedRun $run): string
    {
        $lines = [];
        foreach ($run->getInput()->getMessages() as $message) {
            $line = match (true) {
                $message instanceof SystemMessage => null,
                $message instanceof UserMessage => 'User: '.$message->asText(),
                $message instanceof AssistantMessage => 'Assistant: '.$message->asText(),
                $message instanceof ToolCallMessage => 'Tool result: '.$message->asText(),
                default => null,
            };

            if (null !== $line) {
                $lines[] = $line;
            }
        }

        foreach ($run->getToolCalls() as $call) {
            $result = $call->getResult();
            $lines[] = \sprintf('Tool call: %s(%s) => %s', $call->getName(), json_encode($call->getArguments()), \is_scalar($result) ? (string) $result : json_encode($result));
        }

        $lines[] = 'Assistant (final answer): '.($run->getAnswer() ?? '(no answer)');

        return implode("\n", $lines);
    }
}
