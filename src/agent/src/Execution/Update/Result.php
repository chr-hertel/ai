<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution\Update;

use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Agent\Execution\UpdateType;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * The terminal update of an agent execution, wrapping the final platform result.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Result implements UpdateInterface
{
    /**
     * @param (\Closure(): ?MessageBag)|MessageBag|null $messages built lazily, since not every result maps to an assistant message
     */
    public function __construct(
        private readonly ResultInterface $result,
        private \Closure|MessageBag|null $messages = null,
    ) {
    }

    public function getType(): UpdateType
    {
        return UpdateType::Result;
    }

    public function getResult(): ResultInterface
    {
        return $this->result;
    }

    /**
     * The messages of the execution, ending with the final answer, null if the agent doesn't provide them.
     */
    public function getMessages(): ?MessageBag
    {
        if ($this->messages instanceof \Closure) {
            $this->messages = ($this->messages)();
        }

        return $this->messages;
    }
}
