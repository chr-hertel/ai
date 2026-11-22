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
     * A copy of this tool call, held back until it gets resolved.
     */
    public function suspend(?string $reason = null): self
    {
        return new self($this->id, $this->name, $this->arguments, $this->signature, ToolCallState::Suspended, $reason);
    }

    /**
     * A copy of this tool call as requested by the model, e.g. to execute it once it got resolved.
     *
     * @param array<string, mixed>|null $arguments replace the arguments of the model
     */
    public function resume(?array $arguments = null): self
    {
        return new self($this->id, $this->name, $arguments ?? $this->arguments, $this->signature);
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
     * Why the tool call is suspended.
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
