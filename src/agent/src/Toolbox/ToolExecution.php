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

use Symfony\AI\Platform\Message\MessageInterface;

/**
 * The outcome of executing the tool calls requested by the model: the messages to append to the
 * conversation, and the raw results the tools returned.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolExecution
{
    /**
     * @param list<MessageInterface> $messages
     * @param list<ToolResult>       $results
     */
    public function __construct(
        private readonly array $messages,
        private readonly array $results,
    ) {
    }

    /**
     * @return list<MessageInterface>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return list<ToolResult>
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
