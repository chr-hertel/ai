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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Simulates the commentary phase of the OpenAI Responses API for platforms that lack it.
 *
 * Every tool gets an additional argument the model fills with a user-facing note on why it calls the
 * tool. The argument is stripped before the call reaches the inner toolbox, so the tools themselves
 * stay untouched, and the agent reports the note as commentary - the same deltas a platform with a
 * native commentary phase streams.
 *
 * A tool that already has a parameter of that name is offered unchanged, without commentary.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CommentaryToolbox implements ToolboxInterface
{
    public const DEFAULT_ARGUMENT = 'commentary';

    public const DEFAULT_DESCRIPTION = 'A short note for the user on why you are calling this tool and what you expect from it, written in the language of the conversation. It is shown to the user while the tool runs.';

    /**
     * Names of the tools whose own parameter is named like the commentary argument, so it must not be stripped.
     *
     * @var array<string, true>
     */
    private array $undecoratedTools = [];

    public function __construct(
        private readonly ToolboxInterface $innerToolbox,
        private readonly string $argument = self::DEFAULT_ARGUMENT,
        private readonly string $description = self::DEFAULT_DESCRIPTION,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function getTools(): array
    {
        return array_map($this->withCommentary(...), $this->innerToolbox->getTools());
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        $arguments = $toolCall->getArguments();
        if (!\array_key_exists($this->argument, $arguments) || isset($this->undecoratedTools[$toolCall->getName()])) {
            return $this->innerToolbox->execute($toolCall);
        }

        unset($arguments[$this->argument]);

        $result = $this->innerToolbox->execute(
            new ToolCall($toolCall->getId(), $toolCall->getName(), $arguments, $toolCall->getSignature()),
        );

        // the model's tool call, commentary included, stays the one the result belongs to
        return new ToolResult($toolCall, $result->getResult(), $result->getSources());
    }

    private function withCommentary(Tool $tool): Tool
    {
        $parameters = $tool->getParameters() ?? ['type' => 'object', 'properties' => [], 'required' => [], 'additionalProperties' => false];

        if (isset($parameters['properties'][$this->argument])) {
            $this->undecoratedTools[$tool->getName()] = true;
            $this->logger->warning('Tool "{tool}" already has a parameter named "{argument}", it is offered without commentary.', [
                'tool' => $tool->getName(),
                'argument' => $this->argument,
            ]);

            return $tool;
        }

        unset($this->undecoratedTools[$tool->getName()]);

        $parameters['properties'][$this->argument] = ['type' => 'string', 'description' => $this->description];
        $parameters['required'] = [...$parameters['required'] ?? [], $this->argument];

        return new Tool(
            $tool->getReference(),
            $tool->getName(),
            $tool->getDescription(),
            $parameters,
            [...$tool->getMetadata(), ToolCallCommentary::METADATA_KEY => $this->argument],
        );
    }
}
