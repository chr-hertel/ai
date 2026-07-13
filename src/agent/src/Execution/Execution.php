<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution;

use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * A lazy, iterable agent execution.
 *
 * It can be consumed in three ways:
 *  - foreach ($execution as $update) { ... }              (process-style)
 *  - $execution->onProgress(...)->onResult(...)->await()  (callback-style)
 *  - $execution->await()                                  (synchronous result)
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @implements \IteratorAggregate<int, UpdateInterface>
 */
final class Execution implements \IteratorAggregate
{
    /**
     * @var list<callable(Progress): void>
     */
    private array $progressCallbacks = [];

    /**
     * @var list<callable(Result): void>
     */
    private array $resultCallbacks = [];

    private bool $consumed = false;

    /**
     * @param \Closure(): \Generator<int, UpdateInterface, mixed, void> $factory
     */
    public function __construct(
        private readonly \Closure $factory,
    ) {
    }

    /**
     * @return \Generator<int, UpdateInterface, mixed, void>
     */
    public function getIterator(): \Generator
    {
        return $this->consume();
    }

    /**
     * @param callable(Progress): void $callback
     */
    public function onProgress(callable $callback): self
    {
        $this->progressCallbacks[] = $callback;

        return $this;
    }

    /**
     * @param callable(Result): void $callback
     */
    public function onResult(callable $callback): self
    {
        $this->resultCallbacks[] = $callback;

        return $this;
    }

    /**
     * Drives the execution to completion and returns the final result.
     */
    public function await(): ResultInterface
    {
        $result = null;

        foreach ($this->consume() as $update) {
            if ($update instanceof Result) {
                $result = $update->getResult();
                foreach ($this->resultCallbacks as $callback) {
                    $callback($update);
                }

                continue;
            }

            if ($update instanceof Progress) {
                foreach ($this->progressCallbacks as $callback) {
                    $callback($update);
                }
            }
        }

        if (null === $result) {
            throw new RuntimeException('The agent execution finished without producing a result.');
        }

        return $result;
    }

    /**
     * An execution runs the agent, including its side effects — consuming it
     * twice would silently run the agent twice.
     *
     * @return \Generator<int, UpdateInterface, mixed, void>
     */
    private function consume(): \Generator
    {
        if ($this->consumed) {
            throw new LogicException('The execution was already consumed. Call Agent::run() again for a new execution.');
        }

        $this->consumed = true;

        return ($this->factory)();
    }
}
