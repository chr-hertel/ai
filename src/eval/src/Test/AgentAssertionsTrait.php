<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Test;

use PHPUnit\Framework\Assert;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\Evaluator\ToolArguments;
use Symfony\AI\Eval\RecordedRun;

/**
 * PHPUnit assertions on agent behavior, for runs recorded with the RunRecorder.
 *
 * Combine them with a cassette of the RecordingProvider or the InMemoryPlatform to keep the tests deterministic.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
trait AgentAssertionsTrait
{
    public static function assertRunSucceeded(RecordedRun $run): void
    {
        $error = $run->getError();
        Assert::assertNull($error, null !== $error ? \sprintf('The run failed with %s: %s', $error::class, $error->getMessage()) : '');
    }

    public static function assertToolCalled(string $tool, RecordedRun $run): void
    {
        Assert::assertTrue($run->calledTool($tool), \sprintf('Expected the tool "%s" to be called, called: "%s".', $tool, implode('", "', $run->toolSequence())));
    }

    public static function assertToolNotCalled(string $tool, RecordedRun $run): void
    {
        Assert::assertFalse($run->calledTool($tool), \sprintf('Expected the tool "%s" not to be called.', $tool));
    }

    /**
     * @param list<string> $tools
     */
    public static function assertToolsCalledInOrder(array $tools, RecordedRun $run): void
    {
        Assert::assertSame($tools, $run->toolSequence(), 'The tools were not called in the expected order.');
    }

    /**
     * @param array<string, mixed> $arguments values enclosed in slashes are matched as regular expression
     */
    public static function assertToolCalledWith(string $tool, array $arguments, RecordedRun $run): void
    {
        $score = (new ToolArguments())->evaluate(new EvalCase('assertion', '', expectations: ['tool_arguments' => [$tool => $arguments]]), $run);

        Assert::assertTrue(null !== $score && $score->isPassed(), $score?->getExplanation() ?? '');
    }

    public static function assertAnswerContains(string $needle, RecordedRun $run): void
    {
        Assert::assertStringContainsStringIgnoringCase($needle, $run->getAnswer() ?? '');
    }

    public static function assertAnswerNotContains(string $needle, RecordedRun $run): void
    {
        Assert::assertStringNotContainsStringIgnoringCase($needle, $run->getAnswer() ?? '');
    }

    public static function assertAnswerMatches(string $pattern, RecordedRun $run): void
    {
        Assert::assertMatchesRegularExpression($pattern, $run->getAnswer() ?? '');
    }

    public static function assertMaxToolCalls(int $max, RecordedRun $run): void
    {
        Assert::assertLessThanOrEqual($max, \count($run->getToolCalls()), \sprintf('Expected at most %d tool calls, got %d.', $max, \count($run->getToolCalls())));
    }
}
