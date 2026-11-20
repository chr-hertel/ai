<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Online;

use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\EvaluatorInterface;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\FeedbackRecorderInterface;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\Feedback\Source;

/**
 * Scores a sampled production run and records every score as feedback, next to the feedback of humans.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EvaluateRunHandler
{
    /**
     * @param iterable<EvaluatorInterface> $evaluators
     */
    public function __construct(
        private readonly iterable $evaluators,
        private readonly FeedbackRecorderInterface $recorder,
    ) {
    }

    public function __invoke(EvaluateRun $message): void
    {
        $case = new EvalCase('online-'.$message->getRunId(), '', expectations: $message->getExpectations(), sourceRun: $message->getRunId());

        foreach ($this->evaluators as $evaluator) {
            $score = $evaluator->evaluate($case, $message->getRun());
            if (null === $score) {
                continue;
            }

            $this->recorder->record(new Feedback(
                $message->getRunId(),
                Signal::EvaluatorScore,
                $score->getValue(),
                Source::Evaluator,
                name: $score->getEvaluator(),
                comment: $score->getExplanation(),
                metadata: ['passed' => $score->isPassed()],
            ));
        }
    }
}
