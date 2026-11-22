<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Event;

use Symfony\AI\Agent\Execution\Turn;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallsExecuted
{
    private ResultInterface $result;

    /**
     * @param ToolResult[] $toolResults
     * @param Turn|null    $turn        the round of the model that requested the tool calls, with its token usage
     */
    public function __construct(
        private readonly array $toolResults,
        private readonly ?Turn $turn = null,
        private readonly ?RunContext $runContext = null,
    ) {
    }

    public function hasResult(): bool
    {
        return isset($this->result);
    }

    public function setResult(ResultInterface $result): void
    {
        $this->result = $result;
    }

    public function getResult(): ResultInterface
    {
        return $this->result;
    }

    /**
     * @return ToolResult[]
     */
    public function getToolResults(): array
    {
        return $this->toolResults;
    }

    public function getTurn(): ?Turn
    {
        return $this->turn;
    }

    /**
     * The run that requested the tool call, null if the toolbox runs outside of an agent.
     */
    public function getRunContext(): ?RunContext
    {
        return $this->runContext;
    }
}
