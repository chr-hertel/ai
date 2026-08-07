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
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;

/**
 * A lazy agent execution that is also the {@see ResultInterface} it produces.
 *
 * Returned by {@see \Symfony\AI\Agent\AgentInterface::call()}, it can be consumed in three ways:
 *  - as a result:    $execution->getContent()                              (drives to completion, returns the answer)
 *  - as a process:   foreach ($execution as $update) { ... }               (observe every progress and result update)
 *  - with callbacks: $execution->onProgress(...)->onResult(...)->await()   (drives to completion, returns the result)
 *
 * Consuming drives the agent, including its side effects. The final result is cached, so reading it (via
 * getContent()/await()/getMetadata()) is idempotent; re-iterating a consumed execution throws — call the
 * agent again for a fresh execution.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @implements \IteratorAggregate<int, UpdateInterface>
 */
final class Execution implements \IteratorAggregate, ResultInterface
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

    private ?ResultInterface $result = null;

    private readonly Metadata $metadata;

    /**
     * @param \Closure(): \Generator<int, UpdateInterface, mixed, void> $factory
     * @param bool                                                      $streamed whether the answer is streamed, in which case getContent() yields the deltas
     */
    public function __construct(
        private readonly \Closure $factory,
        private readonly bool $streamed = false,
    ) {
        $this->metadata = new Metadata();
    }

    /**
     * @return \Generator<int, UpdateInterface, mixed, void>
     */
    public function getIterator(): \Generator
    {
        foreach ($this->consume() as $update) {
            if ($update instanceof Result) {
                $this->result = $update->getResult();
            }

            yield $update;
        }
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
        if (null !== $this->result) {
            return $this->result;
        }

        foreach ($this->consume() as $update) {
            if ($update instanceof Result) {
                $this->result = $update->getResult();
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

        if (null === $this->result) {
            throw new RuntimeException('The agent execution finished without producing a result.');
        }

        return $this->result;
    }

    public function getContent(): string|iterable|object|null
    {
        if ($this->streamed) {
            return $this->deltas();
        }

        return $this->await()->getContent();
    }

    public function getMetadata(): Metadata
    {
        if ($this->streamed) {
            // populated while the deltas are consumed, mirroring a StreamResult
            return $this->metadata;
        }

        return $this->await()->getMetadata();
    }

    public function getRawResult(): ?RawResultInterface
    {
        return $this->await()->getRawResult();
    }

    public function setRawResult(RawResultInterface $rawResult): void
    {
        $this->await()->setRawResult($rawResult);
    }

    /**
     * Yields the streamed deltas of the answer, driving the execution as they arrive.
     *
     * @return \Generator<int, DeltaInterface, mixed, void>
     */
    private function deltas(): \Generator
    {
        foreach ($this->consume() as $update) {
            if ($update instanceof Result) {
                // the final result carries the metadata aggregated over all rounds, e.g. token usage
                $this->result = $update->getResult();
                $this->metadata->merge($update->getResult()->getMetadata());

                continue;
            }

            if ($update instanceof Progress) {
                foreach ($this->progressCallbacks as $callback) {
                    $callback($update);
                }

                if ('delta' === $update->getStage() && $update->getPayload() instanceof DeltaInterface) {
                    yield $update->getPayload();
                }
            }
        }
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
            throw new LogicException('The execution was already consumed. Call the agent again for a new execution.');
        }

        $this->consumed = true;

        return ($this->factory)();
    }
}
