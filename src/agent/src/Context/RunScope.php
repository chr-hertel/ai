<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context;

/**
 * Makes the run context of the agent run available while its tools execute.
 *
 * Toolboxes only receive a tool call, so tool call events and guardrails read the run they belong to from this
 * scope. The runner enters it for each step of the tool execution only, so interleaved runs (parallel executions,
 * fibers) never see each other's run.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RunScope
{
    private static ?RunContext $current = null;

    /**
     * @template T
     *
     * @param \Closure(): T $callback
     *
     * @return T
     */
    public static function run(?RunContext $runContext, \Closure $callback): mixed
    {
        $previous = self::$current;
        self::$current = $runContext;

        try {
            return $callback();
        } finally {
            self::$current = $previous;
        }
    }

    public static function current(): ?RunContext
    {
        return self::$current;
    }
}
