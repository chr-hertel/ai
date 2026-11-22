<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Simulation;

use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SimulatedConversation
{
    public function __construct(
        private readonly MessageBag $messages,
        private readonly int $turns,
        private readonly bool $goalReached,
    ) {
    }

    public function getMessages(): MessageBag
    {
        return $this->messages;
    }

    public function getTurns(): int
    {
        return $this->turns;
    }

    public function isGoalReached(): bool
    {
        return $this->goalReached;
    }
}
