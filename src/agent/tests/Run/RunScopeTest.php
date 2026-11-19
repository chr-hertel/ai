<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Run;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Agent\Run\RunScope;

final class RunScopeTest extends TestCase
{
    public function testScopeIsRestoredAfterTheCallback()
    {
        $run = new RunContext('run-1');

        $this->assertSame($run, RunScope::run($run, static fn (): ?RunContext => RunScope::current()));
        $this->assertNull(RunScope::current());
    }

    public function testSuspendedFibersKeepTheirOwnScope()
    {
        $runA = new RunContext('run-a');
        $runB = new RunContext('run-b');
        $seen = [];

        $fiberA = new \Fiber(static function () use ($runA, &$seen): void {
            RunScope::run($runA, static function () use (&$seen): void {
                \Fiber::suspend();
                $seen['a'] = RunScope::current()?->getRunId();
            });
        });
        $fiberB = new \Fiber(static function () use ($runB, &$seen): void {
            RunScope::run($runB, static function () use (&$seen): void {
                \Fiber::suspend();
                $seen['b'] = RunScope::current()?->getRunId();
            });
        });

        $fiberA->start();
        $fiberB->start();
        $seen['outside'] = RunScope::current()?->getRunId();
        $fiberA->resume();
        $fiberB->resume();

        $this->assertSame(['outside' => null, 'a' => 'run-a', 'b' => 'run-b'], $seen);
        $this->assertNull(RunScope::current());
    }

    public function testFiberWithoutOwnScopeSeesTheScopeOutsideOfFibers()
    {
        $run = new RunContext('run-1');

        $seen = RunScope::run($run, static function (): ?string {
            $fiber = new \Fiber(static fn (): ?string => RunScope::current()?->getRunId());
            $fiber->start();

            return $fiber->getReturn();
        });

        $this->assertSame('run-1', $seen);
    }
}
