<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Toolbox;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Eval\Tests\Fixtures\SupportTools;
use Symfony\AI\Eval\Toolbox\FixtureToolbox;
use Symfony\AI\Platform\Result\ToolCall;

final class FixtureToolboxTest extends TestCase
{
    public function testAnswersToolCallsWithFixtures()
    {
        $toolbox = new FixtureToolbox(new Toolbox([new SupportTools()]), [
            'order_lookup' => ['SO-1' => ['status' => 'delivered'], '*' => ['status' => 'unknown']],
            'open_refund' => 'RF-4711',
        ]);

        $this->assertSame(['order_lookup', 'open_refund'], array_map(static fn ($tool): string => $tool->getName(), $toolbox->getTools()));
        $this->assertSame(['status' => 'delivered'], $toolbox->execute(new ToolCall('1', 'order_lookup', ['orderNumber' => 'SO-1']))->getResult());
        $this->assertSame(['status' => 'unknown'], $toolbox->execute(new ToolCall('2', 'order_lookup', ['orderNumber' => 'SO-2']))->getResult());
        $this->assertSame('RF-4711', $toolbox->execute(new ToolCall('3', 'open_refund', ['orderNumber' => 'SO-1', 'reason' => 'x']))->getResult());
    }

    public function testMissingFixtureIsReportedToTheModel()
    {
        $toolbox = new FixtureToolbox(new Toolbox([new SupportTools()]), ['order_lookup' => ['SO-1' => 'delivered']]);

        $this->assertSame('Nothing found for "SO-2".', $toolbox->execute(new ToolCall('1', 'order_lookup', ['orderNumber' => 'SO-2']))->getResult());
        $this->assertSame('No result available for tool "open_refund".', $toolbox->execute(new ToolCall('2', 'open_refund', ['orderNumber' => 'SO-1', 'reason' => 'x']))->getResult());
    }

    public function testUnknownToolIsNotFound()
    {
        $this->expectException(ToolNotFoundException::class);

        (new FixtureToolbox(new Toolbox([new SupportTools()]), []))->execute(new ToolCall('1', 'unknown'));
    }
}
