<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Evaluator;

use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\EvaluatorInterface;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\Score;

/**
 * Checks the final answer against "expect.contains", "expect.not_contains" (case-insensitive) and "expect.matches" (regular expression).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextAssertions implements EvaluatorInterface
{
    public function getName(): string
    {
        return 'text';
    }

    public function evaluate(EvalCase $case, RecordedRun $run): ?Score
    {
        $contains = (array) $case->expectation('contains', []);
        $notContains = (array) $case->expectation('not_contains', []);
        $matches = (array) $case->expectation('matches', []);

        if ([] === $contains && [] === $notContains && [] === $matches) {
            return null;
        }

        $answer = $run->getAnswer() ?? '';
        $failures = [];

        foreach ($contains as $needle) {
            if (false === mb_stripos($answer, (string) $needle)) {
                $failures[] = \sprintf('Missing "%s".', $needle);
            }
        }

        foreach ($notContains as $needle) {
            if (false !== mb_stripos($answer, (string) $needle)) {
                $failures[] = \sprintf('Unexpected "%s".', $needle);
            }
        }

        foreach ($matches as $pattern) {
            if (1 !== preg_match((string) $pattern, $answer)) {
                $failures[] = \sprintf('No match for %s.', $pattern);
            }
        }

        $checks = \count($contains) + \count($notContains) + \count($matches);

        return [] === $failures
            ? Score::pass($this->getName())
            : new Score($this->getName(), 1 - \count($failures) / $checks, false, implode(' ', $failures));
    }
}
