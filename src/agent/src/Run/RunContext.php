<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Run;

use Symfony\AI\Agent\Prompt\PromptReference;

/**
 * Identifies a single agent run and describes what it was running.
 *
 * Every invocation carries exactly one run context: the caller may pass one as "run_context" option, otherwise the
 * agent creates it. Tracing, feedback, evaluation and cost reporting all join on its run ID. An empty run ID is
 * replaced by the ID of the execution, and a tracing decorator records the trace the run belongs to as trace ID.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RunContext
{
    /**
     * @param array<string, string> $attributes free-form, low-cardinality attributes (tenant, channel, locale, ...)
     */
    public function __construct(
        private readonly string $runId = '',
        private readonly ?string $userId = null,
        private readonly ?string $release = null,
        private readonly ?PromptReference $prompt = null,
        private readonly array $attributes = [],
        private readonly ?string $traceId = null,
    ) {
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    /**
     * The trace the run is part of, shared with other runs of the same request or of nested agents.
     */
    public function getTraceId(): ?string
    {
        return $this->traceId;
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
        return new self($runId, $this->userId, $this->release, $this->prompt, $this->attributes, $this->traceId);
    }

    public function withRelease(?string $release): self
    {
        return new self($this->runId, $this->userId, $release, $this->prompt, $this->attributes, $this->traceId);
    }

    public function withPrompt(?PromptReference $prompt): self
    {
        return new self($this->runId, $this->userId, $this->release, $prompt, $this->attributes, $this->traceId);
    }

    public function withTraceId(?string $traceId): self
    {
        return new self($this->runId, $this->userId, $this->release, $this->prompt, $this->attributes, $traceId);
    }

    public function withAttribute(string $key, string $value): self
    {
        return new self($this->runId, $this->userId, $this->release, $this->prompt, [...$this->attributes, $key => $value], $this->traceId);
    }
}
