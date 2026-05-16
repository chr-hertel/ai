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

use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * Strategy responsible for executing the tool calls requested by the model.
 *
 * Implementations are generators: they may yield {@see UpdateInterface} objects
 * (progress, human interaction) and must return the list of messages to append
 * to the conversation before the next model invocation.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ToolExecutorInterface
{
    /**
     * @return \Generator<int, UpdateInterface, mixed, list<MessageInterface>>
     */
    public function execute(ToolCallResult $result, AgentContext $context): \Generator;
}
