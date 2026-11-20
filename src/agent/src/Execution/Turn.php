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

use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * One round of an agent execution: the messages sent to the model, its result and the results of the tools it requested.
 *
 * The metadata is a snapshot of the round's own metadata, e.g. its finish reason and token usage, taken before the
 * execution aggregates the metadata of all rounds onto its final result.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Turn
{
    private readonly Metadata $metadata;

    /**
     * @param non-empty-string|Model $model
     * @param list<ToolResult>       $toolResults
     */
    public function __construct(
        private readonly string|Model $model,
        private readonly ResultInterface $result,
        private readonly array $toolResults = [],
        private readonly ?float $duration = null,
        private readonly ?MessageBag $messages = null,
        private readonly ?float $toolDuration = null,
    ) {
        $this->metadata = clone $result->getMetadata();
    }

    /**
     * @return non-empty-string|Model
     */
    public function getModel(): string|Model
    {
        return $this->model;
    }

    public function getResult(): ResultInterface
    {
        return $this->result;
    }

    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    /**
     * @return list<ToolResult>
     */
    public function getToolResults(): array
    {
        return $this->toolResults;
    }

    /**
     * Seconds from sending the request to the model until its result was fully received, tool execution excluded.
     */
    public function getDuration(): ?float
    {
        return $this->duration;
    }

    /**
     * The messages the model got in this round, including the tool results of the rounds before.
     */
    public function getMessages(): ?MessageBag
    {
        return $this->messages;
    }

    /**
     * Seconds the tool calls of this round took, null if the model requested none.
     */
    public function getToolDuration(): ?float
    {
        return $this->toolDuration;
    }

    public function hasToolResults(): bool
    {
        return [] !== $this->toolResults;
    }
}
