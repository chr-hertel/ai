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
use Symfony\AI\Agent\Toolbox\Exception\ToolInteractionException;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ToolboxInterface
{
    /**
     * @return Tool[]
     */
    public function getTools(): array;

    /**
     * @throws ToolExecutionExceptionInterface if the tool execution fails
     * @throws ToolNotFoundException           if the tool is not found
     * @throws ToolInteractionException        if the tool pauses the execution to ask a human
     */
    public function execute(ToolCall $toolCall): ToolResult;
}
