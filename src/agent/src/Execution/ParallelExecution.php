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

use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Fans out several agent {@see Execution}s and exposes their merged update
 * stream. Updates and results are keyed by the execution key.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @implements \IteratorAggregate<int|string, UpdateInterface>
 */
final class ParallelExecution implements \IteratorAggregate
{
    /**
     * @param array<int|string, Execution> $executions
     */
    public function __construct(
        private readonly array $executions,
    ) {
    }

    /**
     * @return \Generator<int|string, UpdateInterface>
     */
    public function getIterator(): \Generator
    {
        foreach ($this->executions as $key => $execution) {
            foreach ($execution as $update) {
                yield $key => $update;
            }
        }
    }

    /**
     * Drives every execution to completion and returns the results, keyed by
     * the execution key.
     *
     * @return array<int|string, ResultInterface>
     */
    public function await(): array
    {
        $results = [];
        foreach ($this->executions as $key => $execution) {
            $results[$key] = $execution->await();
        }

        return $results;
    }
}
