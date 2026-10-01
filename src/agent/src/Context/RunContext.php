<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context;

/**
 * Identifies a single agent run and describes what it was running.
 *
 * Every invocation carries exactly one run context: the caller may pass one through the context, otherwise the agent
 * creates it. Tracing, feedback, evaluation and cost reporting all join on its run ID, which is why a tracing
 * decorator seeds it with the trace ID before the agent runs.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RunContext
{
    /**
     * @param array<string, string> $attributes free-form, low-cardinality attributes (tenant, channel, locale, ...)
     */
    public function __construct(
        private readonly string $runId,
        private readonly ?string $userId = null,
        private readonly ?string $release = null,
        private readonly ?PromptReference $prompt = null,
        private readonly array $attributes = [],
    ) {
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getRelease(): ?string
    {
        return $this->release;
    }

    public function getPrompt(): ?PromptReference
    {
        return $this->prompt;
    }

    /**
     * @return array<string, string>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function withRunId(string $runId): self
    {
        return new self($runId, $this->userId, $this->release, $this->prompt, $this->attributes);
    }

    public function withRelease(?string $release): self
    {
        return new self($this->runId, $this->userId, $release, $this->prompt, $this->attributes);
    }

    public function withPrompt(?PromptReference $prompt): self
    {
        return new self($this->runId, $this->userId, $this->release, $prompt, $this->attributes);
    }

    public function withAttribute(string $key, string $value): self
    {
        return new self($this->runId, $this->userId, $this->release, $this->prompt, [...$this->attributes, $key => $value]);
    }
}
