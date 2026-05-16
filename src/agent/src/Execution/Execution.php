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

use Symfony\AI\Agent\Exception\InteractionRequiredException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Update\Interaction;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * A lazy, iterable agent execution.
 *
 * It can be consumed in three ways:
 *  - foreach ($execution as $update) { ... }            (process-style)
 *  - $execution->onProgress(...)->onResult(...)->await() (callback-style)
 *  - $execution->await()                                (synchronous result)
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
     * @var list<callable(Interaction): (InteractionResponse|null)>
     */
    private array $interactionCallbacks = [];

    /**
     * @var list<callable(Result): void>
     */
    private array $resultCallbacks = [];

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
        return ($this->factory)();
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
     * @param callable(Interaction): (InteractionResponse|null) $callback
     */
    public function onInteraction(callable $callback): self
    {
        $this->interactionCallbacks[] = $callback;

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
     *
     * @throws InteractionRequiredException when the execution pauses for human
     *                                      interaction but no handler is registered
     */
    public function await(): ResultInterface
    {
        $generator = ($this->factory)();
        $result = null;

        while ($generator->valid()) {
            $update = $generator->current();

            if ($update instanceof Interaction) {
                if ([] === $this->interactionCallbacks) {
                    throw new InteractionRequiredException($update);
                }

                $response = null;
                foreach ($this->interactionCallbacks as $callback) {
                    $returned = $callback($update);
                    if ($returned instanceof InteractionResponse) {
                        $response = $returned;
                    }
                }

                $generator->send($response ?? new InteractionResponse());

                continue;
            }

            if ($update instanceof Result) {
                $result = $update->getResult();
                foreach ($this->resultCallbacks as $callback) {
                    $callback($update);
                }
            } elseif ($update instanceof Progress) {
                foreach ($this->progressCallbacks as $callback) {
                    $callback($update);
                }
            }

            $generator->next();
        }

        if (null === $result) {
            throw new RuntimeException('The agent execution finished without producing a result.');
        }

        return $result;
    }
}
