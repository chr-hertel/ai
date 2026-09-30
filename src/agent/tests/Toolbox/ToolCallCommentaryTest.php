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
use Symfony\AI\Agent\Toolbox\ToolCallCommentary;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class ToolCallCommentaryTest extends TestCase
{
    public function testCommentaryIsExtractedInCallOrder()
    {
        $tools = [
            new Tool(new ExecutionReference('Weather'), 'weather', 'Current weather', metadata: [ToolCallCommentary::METADATA_KEY => 'commentary']),
            new Tool(new ExecutionReference('Clock'), 'clock', 'Current time', metadata: [ToolCallCommentary::METADATA_KEY => 'reason']),
        ];

        $this->assertSame(['What time is it there?', 'Checking the weather.'], ToolCallCommentary::extract($tools, [
            new ToolCall('call_1', 'clock', ['reason' => 'What time is it there?']),
            new ToolCall('call_2', 'weather', ['city' => 'Berlin', 'commentary' => 'Checking the weather.']),
        ]));
    }

    public function testToolWithoutCommentaryMetadataIsIgnored()
    {
        $tools = [new Tool(new ExecutionReference('Review'), 'review', 'Reviews code')];

        $this->assertSame([], ToolCallCommentary::extract($tools, [
            new ToolCall('call_1', 'review', ['commentary' => 'Looks good to me.']),
        ]));
    }

    public function testMissingBlankOrNonStringCommentaryIsSkipped()
    {
        $tools = [new Tool(new ExecutionReference('Weather'), 'weather', 'Current weather', metadata: [ToolCallCommentary::METADATA_KEY => 'commentary'])];

        $this->assertSame([], ToolCallCommentary::extract($tools, [
            new ToolCall('call_1', 'weather', ['city' => 'Berlin']),
            new ToolCall('call_2', 'weather', ['commentary' => '  ']),
            new ToolCall('call_3', 'weather', ['commentary' => ['not', 'a', 'string']]),
            new ToolCall('call_4', 'unknown', ['commentary' => 'No such tool.']),
        ]));
    }
}
