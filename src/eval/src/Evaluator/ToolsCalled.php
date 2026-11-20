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
 * Checks the tools the agent called against "expect.tools_called".
 *
 * With "expect.tool_order: strict" the tools must be called in exactly that order, otherwise every expected tool
 * must be called at least once. An empty list expects no tool call at all.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolsCalled implements EvaluatorInterface
{
    public function getName(): string
    {
        return 'tools_called';
    }

    public function evaluate(EvalCase $case, RecordedRun $run): ?Score
    {
        $expected = $case->expectation('tools_called');
        if (!\is_array($expected)) {
            return null;
        }

        $actual = $run->toolSequence();

        if ([] === $expected) {
            return [] === $actual
                ? Score::pass($this->getName())
                : Score::fail($this->getName(), \sprintf('Expected no tool call, got "%s".', implode('", "', $actual)));
        }

        if ('strict' === $case->expectation('tool_order')) {
            return $actual === $expected
                ? Score::pass($this->getName())
                : Score::fail($this->getName(), \sprintf('Expected the tool calls "%s", got "%s".', implode(' > ', $expected), implode(' > ', $actual)));
        }

        $missing = array_values(array_diff($expected, $actual));
        if ([] === $missing) {
            return Score::pass($this->getName());
        }

        return new Score($this->getName(), 1 - \count($missing) / \count($expected), false, \sprintf('The tools "%s" were not called.', implode('", "', $missing)));
    }
}
