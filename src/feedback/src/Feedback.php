<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback;

use Symfony\AI\Feedback\Exception\InvalidArgumentException;

/**
 * A single piece of feedback on an agent run.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Feedback
{
    /**
     * @param string                     $runId     the run ID of the agent's run context, the trace ID when tracing is enabled
     * @param string|null                $name      the score name in the backend, defaults to the signal, e.g. "thumbs" or the evaluator name
     * @param string|null                $messageId ID of the rated message, if the feedback is about a single turn
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        private readonly string $runId,
        private readonly Signal $signal,
        private readonly float|int|bool|string|null $value = null,
        private readonly Source $source = Source::User,
        private readonly ?string $name = null,
        private readonly ?string $messageId = null,
        private readonly ?string $comment = null,
        private readonly array $metadata = [],
        private readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
        if ('' === $runId) {
            throw new InvalidArgumentException('The run ID of a feedback must not be empty.');
        }
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getSignal(): Signal
    {
        return $this->signal;
    }

    public function getValue(): float|int|bool|string|null
    {
        return $this->value;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getName(): string
    {
        return $this->name ?? $this->signal->value;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Whether the feedback says the run went well, null if it does not tell.
     */
    public function isPositive(): ?bool
    {
        return match (true) {
            \is_bool($this->value) => $this->value,
            \is_int($this->value), \is_float($this->value) => $this->value >= 0.5,
            default => null,
        };
    }
}
