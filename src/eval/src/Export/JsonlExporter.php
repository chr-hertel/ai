<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Export;

use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Exports curated runs as chat JSONL, the format of most eval frameworks and fine-tuning APIs.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class JsonlExporter
{
    /**
     * @param iterable<RecordedRun> $runs
     */
    public function export(iterable $runs): string
    {
        $lines = [];
        foreach ($runs as $run) {
            $messages = [];
            foreach ($run->getInput()->getMessages() as $message) {
                $role = match (true) {
                    $message instanceof SystemMessage => 'system',
                    $message instanceof UserMessage => 'user',
                    $message instanceof AssistantMessage => 'assistant',
                    $message instanceof ToolCallMessage => 'tool',
                    default => null,
                };

                if (null === $role) {
                    continue;
                }

                $content = $message instanceof SystemMessage ? (string) $message->getContent() : (string) $message->asText();
                $messages[] = ['role' => $role, 'content' => $content];
            }

            if (null !== $answer = $run->getAnswer()) {
                $messages[] = ['role' => 'assistant', 'content' => $answer];
            }

            $lines[] = json_encode(['messages' => $messages], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        }

        return implode("\n", $lines).([] === $lines ? '' : "\n");
    }
}
