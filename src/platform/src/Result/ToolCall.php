<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Exception\LogicException;
use Symfony\AI\Platform\Message\Content\ContentInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCall implements ContentInterface
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly array $arguments = [],
        private readonly ?string $signature = null,
        private readonly ToolCallState $state = ToolCallState::Requested,
        private readonly ?string $reason = null,
    ) {
    }

    /**
     * A copy of this requested tool call, held back until it gets approved or rejected.
     *
     * @throws LogicException if the tool call is not requested
     */
    public function suspend(?string $reason = null): self
    {
        if (ToolCallState::Requested !== $this->state) {
            throw new LogicException(\sprintf('Only a requested tool call can be suspended, the tool call "%s" with id "%s" is "%s".', $this->name, $this->id, $this->state->value));
        }

        return new self($this->id, $this->name, $this->arguments, $this->signature, ToolCallState::Suspended, $reason);
    }

    /**
     * A copy of this suspended tool call, to be executed by the next agent call.
     *
     * @param array<string, mixed>|null $arguments replace the arguments of the model
     *
     * @throws LogicException if the tool call is not suspended
     */
    public function approve(?array $arguments = null): self
    {
        if (ToolCallState::Suspended !== $this->state) {
            throw new LogicException(\sprintf('Only a suspended tool call can be approved, the tool call "%s" with id "%s" is "%s".', $this->name, $this->id, $this->state->value));
        }

        return new self($this->id, $this->name, $arguments ?? $this->arguments, $this->signature, ToolCallState::Approved);
    }

    /**
     * A copy of this requested or suspended tool call, answered with the reason instead of being executed.
     *
     * @throws LogicException if the tool call is neither requested nor suspended
     */
    public function reject(?string $reason = null): self
    {
        if (ToolCallState::Requested !== $this->state && ToolCallState::Suspended !== $this->state) {
            throw new LogicException(\sprintf('Only a requested or suspended tool call can be rejected, the tool call "%s" with id "%s" is "%s".', $this->name, $this->id, $this->state->value));
        }

        return new self($this->id, $this->name, $this->arguments, $this->signature, ToolCallState::Rejected, $reason);
    }

    public function getState(): ToolCallState
    {
        return $this->state;
    }

    public function isSuspended(): bool
    {
        return ToolCallState::Suspended === $this->state;
    }

    /**
     * Why the tool call is suspended or rejected.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Provider-scoped signature guarding this tool call when replayed on a subsequent turn.
     * Currently only Google Gemini / Vertex AI emit signatures on function-call parts (for
     * parallel calls, only the first part carries one).
     */
    public function getSignature(): ?string
    {
        return $this->signature;
    }
}
