<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Exception;

use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\InteractionReason;

/**
 * Thrown by a tool to pause the execution for human interaction.
 *
 * The {@see \Symfony\AI\Agent\Toolbox\SequentialToolExecutor} converts it into
 * an {@see \Symfony\AI\Agent\Execution\Update\Interaction} update; the value of
 * the {@see \Symfony\AI\Agent\Execution\InteractionResponse} sent back by the
 * consumer becomes the tool call's result.
 *
 * Deliberately not a {@see ToolExecutionExceptionInterface}: an interaction is
 * not a failure and must not be swallowed by fault-tolerant decorators.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolInteractionException extends RuntimeException implements ExceptionInterface
{
    /**
     * @param array<string, mixed> $schema optional schema or list of choices describing the expected response
     */
    public function __construct(
        private readonly string $prompt,
        private readonly InteractionReason $reason = InteractionReason::Input,
        private readonly array $schema = [],
    ) {
        parent::__construct(\sprintf('The tool requires human interaction: "%s".', $prompt));
    }

    public static function askUser(string $prompt, array $schema = []): self
    {
        return new self($prompt, InteractionReason::Input, $schema);
    }

    public static function confirm(string $prompt): self
    {
        return new self($prompt, InteractionReason::Confirmation);
    }

    /**
     * @param list<string> $choices
     */
    public static function choose(string $prompt, array $choices): self
    {
        return new self($prompt, InteractionReason::Choice, ['choices' => $choices]);
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getReason(): InteractionReason
    {
        return $this->reason;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }
}
