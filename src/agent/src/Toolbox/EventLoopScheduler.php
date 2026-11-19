<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Revolt\EventLoop;

/**
 * Cooperative scheduling on the Revolt event loop, used when an agent runs inside an event loop
 * (e.g. in an Amp or ReactPHP application, or a terminal UI built on Symfony Tui).
 *
 * Fibers driven by the event loop must only be suspended and resumed through it. Suspending them with
 * {@see \Fiber::suspend()} directly leaves them without anybody to resume them, and resuming them from
 * elsewhere breaks whatever they were awaiting.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class EventLoopScheduler
{
    /**
     * Whether the current code runs in a fiber of the Revolt event loop, e.g. inside \Amp\async().
     *
     * The loop's state can't tell, since it keeps "running" in its own fiber once anything awaited it.
     * The fiber itself does: the loop runs callbacks in fibers it created from its own closures.
     */
    public static function inLoopFiber(): bool
    {
        if (null === $fiber = \Fiber::getCurrent()) {
            return false;
        }

        if (!class_exists(EventLoop::class)) {
            return false;
        }

        $callable = (new \ReflectionFiber($fiber))->getCallable();
        $scope = (new \ReflectionFunction(\Closure::fromCallable($callable)))->getClosureScopeClass()?->getName() ?? '';

        return str_starts_with($scope, 'Revolt\\EventLoop\\');
    }

    /**
     * Gives every other callback queued on the event loop a turn before continuing.
     */
    public static function yield(): void
    {
        $suspension = EventLoop::getSuspension();
        EventLoop::queue($suspension->resume(...));
        $suspension->suspend();
    }

    /**
     * Runs the given closures concurrently on the event loop and waits for all of them.
     *
     * Each closure runs in its own fiber, so a closure awaiting I/O lets the others make progress.
     * Results keep the keys of the given closures. If closures fail, the error of the first one in
     * order is thrown once all of them finished.
     *
     * @template TKey of array-key
     * @template TResult
     *
     * @param array<TKey, \Closure(): TResult> $closures
     *
     * @return array<TKey, TResult>
     */
    public static function all(array $closures): array
    {
        if ([] === $closures) {
            return [];
        }

        $suspension = EventLoop::getSuspension();
        $pending = \count($closures);
        $results = [];
        $errors = [];

        foreach ($closures as $key => $closure) {
            EventLoop::queue(static function () use ($key, $closure, $suspension, &$pending, &$results, &$errors): void {
                try {
                    $results[$key] = $closure();
                } catch (\Throwable $e) {
                    $errors[$key] = $e;
                }

                if (0 === --$pending) {
                    $suspension->resume();
                }
            });
        }

        $suspension->suspend();

        foreach (array_keys($closures) as $key) {
            if (isset($errors[$key])) {
                throw $errors[$key];
            }
        }

        $ordered = [];
        foreach (array_keys($closures) as $key) {
            $ordered[$key] = $results[$key];
        }

        return $ordered;
    }
}
