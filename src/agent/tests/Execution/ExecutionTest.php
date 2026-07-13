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
use Symfony\AI\Agent\Exception\InteractionRequiredException;
use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\InteractionReason;
use Symfony\AI\Agent\Execution\InteractionResponse;
use Symfony\AI\Agent\Execution\Update\Interaction;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Platform\Result\TextResult;

final class ExecutionTest extends TestCase
{
    public function testAwaitReturnsResult()
    {
        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new TextResult('done'));
        });

        $this->assertSame('done', $execution->await()->getContent());
    }

    public function testInteractionHandlerResponseIsSentBackIntoTheGenerator()
    {
        $received = null;
        $execution = new Execution(static function () use (&$received): \Generator {
            $received = yield new Interaction(InteractionReason::Input, 'Which season?');
            yield new ResultUpdate(new TextResult('done'));
        });

        $result = $execution
            ->onInteraction(static fn (Interaction $interaction): InteractionResponse => new InteractionResponse('Season 5'))
            ->await();

        $this->assertInstanceOf(InteractionResponse::class, $received);
        $this->assertSame('Season 5', $received->getValue());
        $this->assertSame('done', $result->getContent());
    }

    public function testAwaitWithoutInteractionHandlerThrows()
    {
        $interaction = new Interaction(InteractionReason::Input, 'Which season?');
        $execution = new Execution(static function () use ($interaction): \Generator {
            yield $interaction;
            yield new ResultUpdate(new TextResult('never reached'));
        });

        try {
            $execution->await();
            $this->fail('Expected an InteractionRequiredException.');
        } catch (InteractionRequiredException $e) {
            $this->assertSame($interaction, $e->getInteraction());
        }
    }

    public function testConsumingAnExecutionTwiceThrows()
    {
        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new TextResult('done'));
        });

        $execution->await();

        $this->expectException(LogicException::class);

        iterator_to_array($execution, false);
    }

    public function testIteratingAndAwaitingThrows()
    {
        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new TextResult('done'));
        });

        iterator_to_array($execution, false);

        $this->expectException(LogicException::class);

        $execution->await();
    }
}
