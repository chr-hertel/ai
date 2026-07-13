<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Execution;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\ParallelExecution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Platform\Result\TextResult;

final class ParallelExecutionTest extends TestCase
{
    public function testAwaitReturnsResultsKeyedByExecution()
    {
        $parallel = new ParallelExecution([
            'first' => $this->execution('one'),
            'second' => $this->execution('two'),
        ]);

        $results = $parallel->await();

        $this->assertSame(['first', 'second'], array_keys($results));
        $this->assertSame('one', $results['first']->getContent());
        $this->assertSame('two', $results['second']->getContent());
    }

    public function testUpdatesAreInterleavedRoundRobin()
    {
        $parallel = new ParallelExecution([
            'a' => $this->execution('one'),
            'b' => $this->execution('two'),
        ]);

        $keys = [];
        foreach ($parallel as $key => $update) {
            $keys[] = $key;
        }

        $this->assertSame(['a', 'b', 'a', 'b'], $keys);
    }

    private function execution(string $result): Execution
    {
        return new Execution(static function () use ($result): \Generator {
            yield new Progress('step', 'working');
            yield new ResultUpdate(new TextResult($result));
        });
    }
}
