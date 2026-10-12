<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Event;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Dispatched after a delegated agent finished handling a handoff.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HandoffCompleted
{
    public function __construct(
        private readonly AgentInterface $source,
        private readonly AgentInterface $target,
        private readonly ResultInterface $result,
    ) {
    }

    public function getSource(): AgentInterface
    {
        return $this->source;
    }

    public function getTarget(): AgentInterface
    {
        return $this->target;
    }

    public function getResult(): ResultInterface
    {
        return $this->result;
    }
}
