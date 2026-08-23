<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionExceptionInterface;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Invokes a single tool call and returns its result.
 *
 * This is the execution half of the toolbox and the natural granularity for cross-cutting concerns:
 * fault tolerance, tracing, authorization and argument validation all decorate this one method. A
 * {@see ToolExecutorInterface} runs a whole batch of calls by delegating to an invoker per call, so
 * those decorators fire once per call regardless of how the batch is scheduled.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ToolInvokerInterface
{
    /**
     * @throws ToolExecutionExceptionInterface if the tool execution fails
     * @throws ToolNotFoundException           if the tool is not found
     */
    public function execute(ToolCall $toolCall): ToolResult;
}
