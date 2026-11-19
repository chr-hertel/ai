<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox;

use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;
use Symfony\AI\Agent\Toolbox\EventLoopScheduler;

use function Amp\async;
use function Amp\delay;

final class EventLoopSchedulerTest extends TestCase
{
    public function testMainIsNoLoopFiber()
    {
        $this->assertFalse(EventLoopScheduler::inLoopFiber());
    }

    public function testFiberOfTheLoopIsDetected()
    {
        $this->assertTrue(async(EventLoopScheduler::inLoopFiber(...))->await());
    }

    public function testPlainFiberIsNoLoopFiberEvenAfterTheLoopRan()
    {
        // once awaited, the loop keeps "running" in its own fiber, which must not matter
        async(static fn () => null)->await();

        $fiber = new \Fiber(EventLoopScheduler::inLoopFiber(...));
        $fiber->start();

        $this->assertFalse($fiber->getReturn());
    }

    public function testAllRunsClosuresConcurrentlyAndKeepsTheirOrder()
    {
        $log = [];
        $results = async(static function () use (&$log): array {
            return EventLoopScheduler::all([
                'slow' => static function () use (&$log): string {
                    delay(0.05);
                    $log[] = 'slow';

                    return 'a';
                },
                'fast' => static function () use (&$log): string {
                    delay(0.01);
                    $log[] = 'fast';

                    return 'b';
                },
            ]);
        })->await();

        $this->assertSame(['slow' => 'a', 'fast' => 'b'], $results);
        $this->assertSame(['fast', 'slow'], $log);
    }

    public function testAllRethrowsTheFirstErrorAfterAllFinished()
    {
        $finished = false;

        try {
            async(static function () use (&$finished): array {
                return EventLoopScheduler::all([
                    static fn () => throw new \RuntimeException('first'),
                    static function () use (&$finished): void {
                        delay(0.01);
                        $finished = true;
                    },
                    static fn () => throw new \LogicException('second'),
                ]);
            })->await();
            $this->fail('Expected an exception.');
        } catch (\RuntimeException $e) {
            $this->assertSame('first', $e->getMessage());
        }

        $this->assertTrue($finished);
    }

    public function testYieldLetsOtherCallbacksRun()
    {
        $log = [];
        async(static function () use (&$log): void {
            EventLoop::queue(static function () use (&$log): void {
                $log[] = 'other';
            });
            $log[] = 'before';
            EventLoopScheduler::yield();
            $log[] = 'after';
        })->await();

        $this->assertSame(['before', 'other', 'after'], $log);
    }
}
