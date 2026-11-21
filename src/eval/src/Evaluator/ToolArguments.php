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
 * Checks the arguments of tool calls against "expect.tool_arguments", e.g. {open_refund: {orderNumber: SO-10023}}.
 *
 * A value enclosed in slashes is matched as regular expression. At least one call of the tool must match.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolArguments implements EvaluatorInterface
{
    public function getName(): string
    {
        return 'tool_arguments';
    }

    public function evaluate(EvalCase $case, RecordedRun $run): ?Score
    {
        $expected = $case->expectation('tool_arguments');
        if (!\is_array($expected) || [] === $expected) {
            return null;
        }

        $failures = [];
        foreach ($expected as $tool => $arguments) {
            if (!$this->anyCallMatches($run, (string) $tool, (array) $arguments)) {
                $failures[] = \sprintf('No call of "%s" with %s.', $tool, json_encode($arguments));
            }
        }

        return [] === $failures ? Score::pass($this->getName()) : Score::fail($this->getName(), implode(' ', $failures));
    }

    /**
     * @param array<string, mixed> $expected
     */
    private function anyCallMatches(RecordedRun $run, string $tool, array $expected): bool
    {
        foreach ($run->getToolCalls($tool) as $call) {
            $arguments = $call->getArguments();
            foreach ($expected as $name => $value) {
                if (!\array_key_exists($name, $arguments) || !$this->matches($value, $arguments[$name])) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    private function matches(mixed $expected, mixed $actual): bool
    {
        if (\is_string($expected) && \strlen($expected) > 2 && str_starts_with($expected, '/') && str_ends_with($expected, '/')) {
            return \is_scalar($actual) && 1 === preg_match($expected, (string) $actual);
        }

        return $expected == $actual;
    }
}
