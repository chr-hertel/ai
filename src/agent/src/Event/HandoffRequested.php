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

/**
 * Dispatched when an agent is about to hand off to another agent. A listener
 * may override the target agent or cancel the handoff.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HandoffRequested
{
    public function __construct(
        private readonly AgentInterface $source,
        private ?AgentInterface $target,
        private readonly string $reasoning = '',
    ) {
    }

    public function getSource(): AgentInterface
    {
        return $this->source;
    }

    public function getTarget(): ?AgentInterface
    {
        return $this->target;
    }

    public function setTarget(?AgentInterface $target): void
    {
        $this->target = $target;
    }

    public function getReasoning(): string
    {
        return $this->reasoning;
    }
}
