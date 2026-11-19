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

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * The messages injected into a running execution that the model hasn't received yet.
 *
 * The runner hands them to the model at the next round boundary: after the tool calls of a round were
 * executed, or after the final answer, in which case the model gets another round to react. A message
 * never goes into a request that was already sent.
 *
 * @internal
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PendingInjections
{
    /**
     * @var list<UserMessage>
     */
    private array $messages = [];

    private bool $closed = false;

    /**
     * @return bool false if the execution already finished and won't pick up the message anymore
     */
    public function add(string|UserMessage $message): bool
    {
        if ($this->closed) {
            return false;
        }

        $this->messages[] = \is_string($message) ? Message::ofUser($message) : $message;

        return true;
    }

    public function isEmpty(): bool
    {
        return [] === $this->messages;
    }

    /**
     * Returns the messages and removes them.
     *
     * @return list<UserMessage>
     */
    public function take(): array
    {
        $messages = $this->messages;
        $this->messages = [];

        return $messages;
    }

    /**
     * Stops accepting messages and returns the ones the model didn't receive, to send them in a new execution.
     *
     * @return list<UserMessage>
     */
    public function close(): array
    {
        $this->closed = true;

        return $this->take();
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }
}
