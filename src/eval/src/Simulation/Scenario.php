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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Scenario
{
    /**
     * @param \Closure(string): bool|null $goalReached checks an answer of the agent, the simulated user decides if null
     */
    public function __construct(
        private readonly string $persona,
        private readonly string $goal,
        private readonly int $maxTurns = 8,
        private readonly ?\Closure $goalReached = null,
    ) {
    }

    public function getPersona(): string
    {
        return $this->persona;
    }

    public function getGoal(): string
    {
        return $this->goal;
    }

    public function getMaxTurns(): int
    {
        return $this->maxTurns;
    }

    public function isGoalReached(string $answer): ?bool
    {
        return null === $this->goalReached ? null : ($this->goalReached)($answer);
    }
}
