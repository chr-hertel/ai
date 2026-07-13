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
use Symfony\AI\Agent\Context\AgentResult;

/**
 * Dispatched once the agent produced its final result.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AgentInvocationCompleted
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly AgentResult $result,
    ) {
    }

    public function getAgent(): AgentInterface
    {
        return $this->agent;
    }

    public function getResult(): AgentResult
    {
        return $this->result;
    }
}
