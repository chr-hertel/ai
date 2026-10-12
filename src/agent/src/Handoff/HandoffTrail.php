<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Handoff;

/**
 * Context item listing the agents a request was already handed off from, so handoffs cannot run in circles.
 *
 * @internal
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HandoffTrail
{
    /**
     * @param list<string> $agents the names of the agents that handed the request off, in order
     */
    public function __construct(
        private readonly array $agents = [],
    ) {
    }

    public function with(string $agent): self
    {
        return new self([...$this->agents, $agent]);
    }

    public function contains(string $agent): bool
    {
        return \in_array($agent, $this->agents, true);
    }

    public function getDepth(): int
    {
        return \count($this->agents);
    }
}
