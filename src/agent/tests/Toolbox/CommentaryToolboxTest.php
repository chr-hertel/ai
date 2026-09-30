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
use Symfony\AI\Agent\Toolbox\CommentaryToolbox;
use Symfony\AI\Agent\Toolbox\Exception\ToolConfigurationException;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class CommentaryToolboxTest extends TestCase
{
    public function testCommentaryArgumentIsAddedToEveryTool()
    {
        $toolbox = new CommentaryToolbox($this->createToolbox([
            new Tool(new ExecutionReference('Weather'), 'weather', 'Current weather', [
                'type' => 'object',
                'properties' => ['city' => ['type' => 'string', 'description' => 'The city']],
                'required' => ['city'],
                'additionalProperties' => false,
            ], ['category' => 'forecast']),
        ]));

        $tool = $toolbox->getTools()[0];

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string', 'description' => 'The city'],
                'commentary' => ['type' => 'string', 'description' => CommentaryToolbox::DEFAULT_DESCRIPTION],
            ],
            'required' => ['city', 'commentary'],
            'additionalProperties' => false,
        ], $tool->getParameters());
        $this->assertSame(['category' => 'forecast', CommentaryToolbox::METADATA_KEY => 'commentary'], $tool->getMetadata());
        $this->assertSame('weather', $tool->getName());
        $this->assertSame('Current weather', $tool->getDescription());
    }

    public function testToolWithoutParametersGetsTheCommentaryArgument()
    {
        $toolbox = new CommentaryToolbox($this->createToolbox([
            new Tool(new ExecutionReference('Clock'), 'clock', 'Current time'),
        ]), 'reason', 'Why?');

        $this->assertSame([
            'type' => 'object',
            'properties' => ['reason' => ['type' => 'string', 'description' => 'Why?']],
            'required' => ['reason'],
            'additionalProperties' => false,
        ], $toolbox->getTools()[0]->getParameters());
    }

    public function testCollidingParameterNameIsRejected()
    {
        $toolbox = new CommentaryToolbox($this->createToolbox([
            new Tool(new ExecutionReference('Review'), 'review', 'Reviews code', [
                'type' => 'object',
                'properties' => ['commentary' => ['type' => 'string', 'description' => 'The review']],
                'required' => ['commentary'],
                'additionalProperties' => false,
            ]),
        ]));

        $this->expectException(ToolConfigurationException::class);
        $this->expectExceptionMessage('Tool "review" already has a parameter named "commentary"');

        $toolbox->getTools();
    }

    public function testCommentaryArgumentIsStrippedBeforeExecution()
    {
        $source = new Source('Weather Service', 'https://example.com', 'Sunny');
        $inner = $this->createMock(ToolboxInterface::class);
        $inner->expects($this->once())
            ->method('execute')
            ->with(new ToolCall('call_1', 'weather', ['city' => 'Berlin'], 'sig'))
            ->willReturnCallback(static fn (ToolCall $toolCall) => new ToolResult($toolCall, 'Sunny', new SourceCollection([$source])));

        $toolCall = new ToolCall('call_1', 'weather', ['city' => 'Berlin', 'commentary' => 'Checking the weather in Berlin.'], 'sig');
        $result = (new CommentaryToolbox($inner))->execute($toolCall);

        $this->assertSame($toolCall, $result->getToolCall());
        $this->assertSame('Sunny', $result->getResult());
        $this->assertSame([$source], iterator_to_array($result->getSources()));
    }

    public function testToolCallWithoutCommentaryIsPassedAsIs()
    {
        $toolCall = new ToolCall('call_1', 'weather', ['city' => 'Berlin']);
        $expected = new ToolResult($toolCall, 'Sunny');

        $inner = $this->createMock(ToolboxInterface::class);
        $inner->expects($this->once())->method('execute')->with($toolCall)->willReturn($expected);

        $this->assertSame($expected, (new CommentaryToolbox($inner))->execute($toolCall));
    }

    /**
     * @param Tool[] $tools
     */
    private function createToolbox(array $tools): ToolboxInterface
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn($tools);

        return $toolbox;
    }
}
