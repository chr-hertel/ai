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
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * One round of an agent execution: the model's result and the results of the tools it requested.
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
     * @param list<ToolResult> $toolResults
     */
    public function __construct(
        private readonly string $model,
        private readonly ResultInterface $result,
        private readonly array $toolResults = [],
    ) {
        $this->metadata = clone $result->getMetadata();
    }

    public function getModel(): string
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

    public function hasToolResults(): bool
    {
        return [] !== $this->toolResults;
    }
}
