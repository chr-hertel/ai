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

/**
 * Resolves a tool call a suspended execution left unanswered, passed by tool call id with the
 * "tool_call_resolutions" option of the next agent call.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallResolution
{
    /**
     * @param array<string, mixed>|null $arguments
     */
    private function __construct(
        private readonly bool $execute,
        private readonly ?array $arguments = null,
        private readonly ?string $answer = null,
    ) {
    }

    /**
     * Executes the tool call, optionally with replaced arguments.
     *
     * @param array<string, mixed>|null $arguments
     */
    public static function execute(?array $arguments = null): self
    {
        return new self(true, $arguments);
    }

    /**
     * Answers the tool call without executing it, e.g. with the reason a human rejected it.
     */
    public static function answer(string $content): self
    {
        return new self(false, answer: $content);
    }

    public function isExecution(): bool
    {
        return $this->execute;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getArguments(): ?array
    {
        return $this->arguments;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }
}
