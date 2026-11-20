<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Calibration;

/**
 * How well a judge agrees with human labels.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CalibrationReport
{
    /**
     * @param list<array{case: string, human: bool, judge: bool, explanation: string|null}> $disagreements
     */
    public function __construct(
        private readonly string $evaluator,
        private readonly int $total,
        private readonly int $truePasses,
        private readonly int $trueFails,
        private readonly int $falsePasses,
        private readonly int $falseFails,
        private readonly array $disagreements,
    ) {
    }

    public function getEvaluator(): string
    {
        return $this->evaluator;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getAgreement(): float
    {
        return 0 === $this->total ? 0.0 : ($this->truePasses + $this->trueFails) / $this->total;
    }

    /**
     * Agreement corrected for the agreement expected by chance, 1.0 is perfect, 0.0 is chance level.
     */
    public function getKappa(): float
    {
        if (0 === $this->total) {
            return 0.0;
        }

        $observed = $this->getAgreement();
        $judgePass = ($this->truePasses + $this->falsePasses) / $this->total;
        $humanPass = ($this->truePasses + $this->falseFails) / $this->total;
        $expected = $judgePass * $humanPass + (1 - $judgePass) * (1 - $humanPass);

        if (1.0 === $expected) {
            return 1.0;
        }

        return ($observed - $expected) / (1 - $expected);
    }

    /**
     * Share of runs the judge passed although humans failed them, the dangerous kind of disagreement.
     */
    public function getFalsePassRate(): float
    {
        return 0 === $this->total ? 0.0 : $this->falsePasses / $this->total;
    }

    public function getFalseFailRate(): float
    {
        return 0 === $this->total ? 0.0 : $this->falseFails / $this->total;
    }

    /**
     * @return list<array{case: string, human: bool, judge: bool, explanation: string|null}>
     */
    public function getDisagreements(): array
    {
        return $this->disagreements;
    }
}
