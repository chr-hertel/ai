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

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;

/**
 * Defines a possible handoff to another agent.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Handoff
{
    /**
     * @param string        $description natural-language description of when this handoff applies
     * @param (\Closure(): bool)|null $condition optional programmatic guard
     */
    public function __construct(
        private readonly AgentInterface $to,
        private readonly string $description,
        private readonly ?\Closure $condition = null,
    ) {
        if ('' === $description) {
            throw new InvalidArgumentException('A handoff must have a non-empty description.');
        }
    }

    public function getTo(): AgentInterface
    {
        return $this->to;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isApplicable(): bool
    {
        if (null === $this->condition) {
            return true;
        }

        return ($this->condition)();
    }
}
