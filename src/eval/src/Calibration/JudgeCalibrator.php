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

use Symfony\AI\Eval\EvaluatorInterface;

/**
 * Measures the agreement of a model-graded evaluator with human labels, so a drifting judge is noticed before its
 * scores mislead anyone.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class JudgeCalibrator
{
    /**
     * @param iterable<LabeledRun> $labeledRuns
     */
    public function calibrate(EvaluatorInterface $judge, iterable $labeledRuns): CalibrationReport
    {
        $total = $truePasses = $trueFails = $falsePasses = $falseFails = 0;
        $disagreements = [];

        foreach ($labeledRuns as $labeled) {
            $score = $judge->evaluate($labeled->getCase(), $labeled->getRun());
            if (null === $score) {
                continue;
            }

            ++$total;
            $human = $labeled->isHumanPassed();
            $judged = $score->isPassed();

            match (true) {
                $human && $judged => ++$truePasses,
                !$human && !$judged => ++$trueFails,
                !$human && $judged => ++$falsePasses,
                default => ++$falseFails,
            };

            if ($human !== $judged) {
                $disagreements[] = ['case' => $labeled->getCase()->getId(), 'human' => $human, 'judge' => $judged, 'explanation' => $score->getExplanation()];
            }
        }

        return new CalibrationReport($judge->getName(), $total, $truePasses, $trueFails, $falsePasses, $falseFails, $disagreements);
    }
}
