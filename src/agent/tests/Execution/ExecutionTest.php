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
use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\TextResult;

final class ExecutionTest extends TestCase
{
    public function testAwaitReturnsTheFinalResult()
    {
        $result = new TextResult('Done');

        $execution = new Execution(static function () use ($result): \Generator {
            yield new Progress('model_request', 'Invoking model.');
            yield new ResultUpdate($result);
        });

        $this->assertSame($result, $execution->await());
    }

    public function testItIsIterable()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Progress('model_request', 'Invoking model.');
            yield new ResultUpdate(new TextResult('Done'));
        });

        $updates = iterator_to_array($execution, false);

        $this->assertCount(2, $updates);
        $this->assertInstanceOf(Progress::class, $updates[0]);
        $this->assertInstanceOf(ResultUpdate::class, $updates[1]);
    }

    public function testItInvokesTheRegisteredCallbacksWhileAwaiting()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Progress('model_request', 'Invoking model.');
            yield new Progress('tool_call', 'Executing tool "clock".');
            yield new ResultUpdate(new TextResult('Done'));
        });

        $stages = [];
        $results = [];

        $execution
            ->onProgress(static function (Progress $progress) use (&$stages): void {
                $stages[] = $progress->getStage();
            })
            ->onResult(static function (ResultUpdate $update) use (&$results): void {
                $results[] = $update->getResult()->getContent();
            })
            ->await();

        $this->assertSame(['model_request', 'tool_call'], $stages);
        $this->assertSame(['Done'], $results);
    }

    public function testItIsLazyAndOnlyRunsTheAgentWhenConsumed()
    {
        $state = new \ArrayObject(['runs' => 0]);

        $execution = new Execution(static function () use ($state): \Generator {
            ++$state['runs'];

            yield new ResultUpdate(new TextResult('Done'));
        });

        $this->assertSame(0, $state['runs']);

        $execution->await();

        $this->assertSame(1, $state['runs']);
    }

    public function testAwaitIsIdempotentAndDoesNotRerunTheAgent()
    {
        $result = new TextResult('Done');
        $state = new \ArrayObject(['runs' => 0]);

        $execution = new Execution(static function () use ($result, $state): \Generator {
            ++$state['runs'];

            yield new ResultUpdate($result);
        });

        $this->assertSame($result, $execution->await());
        $this->assertSame($result, $execution->await());
        $this->assertSame(1, $state['runs']);
    }

    public function testItThrowsWhenIteratedAfterBeingConsumed()
    {
        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new TextResult('Done'));
        });

        $execution->await();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The execution was already consumed. Call the agent again for a new execution.');

        iterator_to_array($execution);
    }

    public function testItActsAsTheResultItProduces()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Progress('model_request', 'Invoking model.');
            yield new ResultUpdate(new TextResult('Hello world'));
        });

        $this->assertInstanceOf(ResultInterface::class, $execution);
        $this->assertSame('Hello world', $execution->getContent());
    }

    public function testStreamedGetContentYieldsTheDeltas()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('Hello '));
            yield new Progress('delta', 'Received a streamed delta.', new TextDelta('world'));
            yield new ResultUpdate(new TextResult('Hello world'));
        }, streamed: true);

        $text = '';
        foreach ($execution->getContent() as $delta) {
            $text .= $delta->getText();
        }

        $this->assertSame('Hello world', $text);
    }

    public function testItThrowsWhenNoResultIsProduced()
    {
        $execution = new Execution(static function (): \Generator {
            yield new Progress('model_request', 'Invoking model.');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The agent execution finished without producing a result.');

        $execution->await();
    }
}
