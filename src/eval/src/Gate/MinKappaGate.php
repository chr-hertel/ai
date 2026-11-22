<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Gate;

use Symfony\AI\Eval\Calibration\JudgeCalibrator;
use Symfony\AI\Eval\Calibration\LabeledRun;
use Symfony\AI\Eval\EvaluatorInterface;
use Symfony\AI\Eval\Report\GateResult;
use Symfony\AI\Eval\Report\Report;

/**
 * Fails if a judge agrees less with human labels than the minimum Cohen's kappa.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MinKappaGate implements GateInterface
{
    /**
     * @param iterable<LabeledRun> $labeledRuns
     */
    public function __construct(
        private readonly EvaluatorInterface $judge,
        private readonly iterable $labeledRuns,
        private readonly float $min,
        private readonly JudgeCalibrator $calibrator = new JudgeCalibrator(),
    ) {
    }

    public function check(Report $report): array
    {
        $calibration = $this->calibrator->calibrate($this->judge, $this->labeledRuns);

        if (0 === $calibration->getTotal()) {
            return [new GateResult('min_kappa', $calibration->getEvaluator(), false, 'kappa not checked, the judge scored none of the labeled runs')];
        }

        $kappa = $calibration->getKappa();
        $passed = $kappa >= $this->min;

        return [new GateResult('min_kappa', $calibration->getEvaluator(), $passed, \sprintf('kappa %.2f %s %.2f', $kappa, $passed ? '>=' : '<', $this->min))];
    }
}
