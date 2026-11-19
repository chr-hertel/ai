<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Run;

/**
 * Makes the run context of the agent run available while it executes.
 *
 * Processors, toolboxes and event listeners only receive their own arguments, so they read the run they belong to
 * from this scope. The agent enters it for each step of its execution only, so interleaved executions never see each
 * other's run. Every fiber has its own scope; a fiber that entered none sees the scope outside of all fibers.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RunScope
{
    private static ?RunContext $main = null;

    /**
     * @var \WeakMap<\Fiber<mixed, mixed, mixed, mixed>, RunContext|null>|null
     */
    private static ?\WeakMap $fibers = null;

    /**
     * @template T
     *
     * @param \Closure(): T $callback
     *
     * @return T
     */
    public static function run(?RunContext $runContext, \Closure $callback): mixed
    {
        $fiber = \Fiber::getCurrent();
        $fibers = self::$fibers ??= new \WeakMap();
        $entered = null !== $fiber && $fibers->offsetExists($fiber);
        $previous = null === $fiber ? self::$main : ($entered ? $fibers[$fiber] : null);

        self::set($fiber, $runContext);

        try {
            return $callback();
        } finally {
            if (null !== $fiber && !$entered) {
                unset($fibers[$fiber]);
            } else {
                self::set($fiber, $previous);
            }
        }
    }

    public static function current(): ?RunContext
    {
        $fiber = \Fiber::getCurrent();

        if (null !== $fiber && null !== self::$fibers && self::$fibers->offsetExists($fiber)) {
            return self::$fibers[$fiber];
        }

        return self::$main;
    }

    /**
     * @param \Fiber<mixed, mixed, mixed, mixed>|null $fiber
     */
    private static function set(?\Fiber $fiber, ?RunContext $runContext): void
    {
        if (null === $fiber) {
            self::$main = $runContext;

            return;
        }

        \assert(null !== self::$fibers);
        self::$fibers[$fiber] = $runContext;
    }
}
