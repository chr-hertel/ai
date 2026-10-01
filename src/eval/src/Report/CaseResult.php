<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Report;

use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\Score;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CaseResult
{
    /**
     * @param list<Score> $scores
     */
    public function __construct(
        private readonly string $variant,
        private readonly EvalCase $case,
        private readonly int $repetition,
        private readonly RecordedRun $run,
        private readonly array $scores,
        private readonly ?float $cost = null,
    ) {
    }

    public function getVariant(): string
    {
        return $this->variant;
    }

    public function getCase(): EvalCase
    {
        return $this->case;
    }

    public function getRepetition(): int
    {
        return $this->repetition;
    }

    public function getRun(): RecordedRun
    {
        return $this->run;
    }

    /**
     * @return list<Score>
     */
    public function getScores(): array
    {
        return $this->scores;
    }

    public function getScore(string $evaluator): ?Score
    {
        foreach ($this->scores as $score) {
            if ($score->getEvaluator() === $evaluator) {
                return $score;
            }
        }

        return null;
    }

    public function getCost(): ?float
    {
        return $this->cost;
    }

    public function isPassed(): bool
    {
        if ($this->run->isFailed()) {
            return false;
        }

        foreach ($this->scores as $score) {
            if (!$score->isPassed()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function getFailureReasons(): array
    {
        if (null !== $error = $this->run->getError()) {
            return [\sprintf('%s: %s', $error::class, $error->getMessage())];
        }

        $reasons = [];
        foreach ($this->scores as $score) {
            if (!$score->isPassed()) {
                $reasons[] = \sprintf('[%s] %s', $score->getEvaluator(), $score->getExplanation() ?? 'failed');
            }
        }

        return $reasons;
    }
}
