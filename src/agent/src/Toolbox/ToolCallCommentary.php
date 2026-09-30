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

use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Contract between a toolbox and the agent for a commentary requested as tool call argument.
 *
 * A toolbox opts a tool in by setting the {@see self::METADATA_KEY} metadata to the name of the argument
 * that carries the commentary, e.g. the {@see CommentaryToolbox}. The agent then reports that argument as
 * commentary before the tool runs, while stripping it before execution is up to the toolbox.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallCommentary
{
    /**
     * Tool metadata key naming the argument that carries the commentary of a tool call.
     */
    public const METADATA_KEY = 'commentary_argument';

    /**
     * @param Tool[]     $tools     the tools the model was offered
     * @param ToolCall[] $toolCalls
     *
     * @return list<string> the non-blank commentary of each tool call, in call order
     */
    public static function extract(array $tools, array $toolCalls): array
    {
        $arguments = [];
        foreach ($tools as $tool) {
            $argument = $tool->getMetadataValue(self::METADATA_KEY);
            if (\is_string($argument)) {
                $arguments[$tool->getName()] = $argument;
            }
        }

        $commentaries = [];
        foreach ($toolCalls as $toolCall) {
            if (!isset($arguments[$toolCall->getName()])) {
                continue;
            }

            $commentary = $toolCall->getArguments()[$arguments[$toolCall->getName()]] ?? null;
            if (\is_string($commentary) && '' !== trim($commentary)) {
                $commentaries[] = $commentary;
            }
        }

        return $commentaries;
    }
}
