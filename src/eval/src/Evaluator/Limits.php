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
 * Checks efficiency limits: "expect.max_tool_calls", "expect.max_tokens" and "expect.max_latency" (seconds).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Limits implements EvaluatorInterface
{
    public function getName(): string
    {
        return 'limits';
    }

    public function evaluate(EvalCase $case, RecordedRun $run): ?Score
    {
        $maxToolCalls = $case->expectation('max_tool_calls');
        $maxTokens = $case->expectation('max_tokens');
        $maxLatency = $case->expectation('max_latency');

        if (null === $maxToolCalls && null === $maxTokens && null === $maxLatency) {
            return null;
        }

        $failures = [];

        if (null !== $maxToolCalls && \count($run->getToolCalls()) > $maxToolCalls) {
            $failures[] = \sprintf('%d tool calls, max %d.', \count($run->getToolCalls()), $maxToolCalls);
        }

        $tokens = $run->getTokenUsage()?->getTotalTokens();
        if (null !== $maxTokens && null !== $tokens && $tokens > $maxTokens) {
            $failures[] = \sprintf('%d tokens, max %d.', $tokens, $maxTokens);
        }

        if (null !== $maxLatency && $run->getDuration() > $maxLatency) {
            $failures[] = \sprintf('%.2fs, max %.2fs.', $run->getDuration(), $maxLatency);
        }

        return [] === $failures ? Score::pass($this->getName()) : Score::fail($this->getName(), implode(' ', $failures));
    }
}
