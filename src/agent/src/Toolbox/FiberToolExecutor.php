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

use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Executes the requested tool calls concurrently using PHP Fibers for cooperative multitasking.
 *
 * All fibers are started before any result is collected, and a round-robin scheduler gives every
 * suspended fiber a turn in each pass. Tool implementations that yield via {@see \Fiber::suspend()}
 * (for example through the {@see SuspendableTrait}) can therefore interleave their setup work before
 * any blocking operation begins. This benefits I/O-bound tools, but note that PHP Fibers do not
 * provide OS-level parallelism within a single process.
 *
 * Results are returned in the same order as the given tool calls, regardless of the order in which
 * the fibers terminate.
 *
 * Inside a running Revolt event loop (Amp, ReactPHP 3, Symfony Tui, ...), the tool calls run as
 * concurrent callbacks of that loop instead, so tools awaiting non-blocking I/O are resumed by the
 * loop and keep making progress while other tools wait.
 *
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class FiberToolExecutor implements ToolExecutorInterface
{
    public function __construct(
        private readonly ToolboxInterface $toolbox,
    ) {
    }

    /**
     * @param ToolCall[] $toolCalls
     *
     * @return \Generator<int, UpdateInterface, mixed, ToolResult[]>
     */
    public function execute(array $toolCalls): \Generator
    {
        foreach ($toolCalls as $toolCall) {
            yield new Progress(Progress::STAGE_TOOL_CALL, \sprintf('Executing tool "%s".', $toolCall->getName()), $toolCall);
        }

        if (EventLoopScheduler::inLoopFiber()) {
            return array_values(EventLoopScheduler::all(array_map(
                fn (ToolCall $toolCall): \Closure => fn (): ToolResult => $this->toolbox->execute($toolCall),
                $toolCalls,
            )));
        }

        $fibers = [];
        foreach ($toolCalls as $index => $toolCall) {
            $fiber = new \Fiber(fn (): ToolResult => $this->toolbox->execute($toolCall));
            $fiber->start();
            $fibers[$index] = $fiber;
        }

        $results = [];
        while ([] !== $fibers) {
            foreach ($fibers as $index => $fiber) {
                if ($fiber->isTerminated()) {
                    $results[$index] = $fiber->getReturn();
                    unset($fibers[$index]);

                    continue;
                }

                $fiber->resume();
            }
        }

        ksort($results);

        return array_values($results);
    }
}
