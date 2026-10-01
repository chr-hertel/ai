<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Test;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Eval\RunRecorder;
use Symfony\AI\Eval\Test\AgentAssertionsTrait;
use Symfony\AI\Eval\Tests\Fixtures\SupportModel;
use Symfony\AI\Eval\Tests\Fixtures\SupportTools;
use Symfony\AI\Eval\Toolbox\FixtureToolbox;
use Symfony\AI\Platform\Test\InMemoryPlatform;

final class AgentAssertionsTraitTest extends TestCase
{
    use AgentAssertionsTrait;

    public function testRefundIsOnlyConfirmedWithTicket()
    {
        $toolbox = new FixtureToolbox(new Toolbox([new SupportTools()]), ['order_lookup' => ['SO-10023' => 'delivered'], 'open_refund' => 'RF-4711']);
        $agent = new Agent(new InMemoryPlatform(\Closure::fromCallable(new SupportModel())), 'support-model', instruction: 'Never promise a refund before open_refund returned a ticket id.', toolbox: $toolbox);

        $run = RunRecorder::record($agent, 'I want my money back for SO-10023, the jacket is too small.');

        self::assertRunSucceeded($run);
        self::assertToolsCalledInOrder(['order_lookup', 'open_refund'], $run);
        self::assertToolCalledWith('open_refund', ['orderNumber' => 'SO-10023'], $run);
        self::assertToolNotCalled('escalate', $run);
        self::assertAnswerMatches('/RF-\d+/', $run);
        self::assertAnswerNotContains('has been processed', $run);
        self::assertMaxToolCalls(3, $run);
        $this->assertCount(3, $run->getTurns());
        $this->assertNotNull($run->getRunId());
    }

    public function testFailingAssertion()
    {
        $run = RunRecorder::record(new Agent(new InMemoryPlatform('Your refund has been processed.'), 'support-model'), 'I want my money back.');

        $this->expectException(AssertionFailedError::class);

        self::assertToolCalled('open_refund', $run);
    }
}
