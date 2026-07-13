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

use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * Executes the tool calls a model requested.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ToolExecutorInterface
{
    public function execute(ToolCallResult $toolCallResult): ToolExecution;
}
