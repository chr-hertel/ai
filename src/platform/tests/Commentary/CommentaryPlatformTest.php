<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Commentary;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Commentary\CommentaryPlatform;
use Symfony\AI\Platform\Message\Content\Commentary;
use Symfony\AI\Platform\Result\CommentaryResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryComplete;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryStart;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class CommentaryPlatformTest extends TestCase
{
    public function testCommentaryArgumentIsAddedToEveryTool()
    {
        $options = [];
        $platform = new CommentaryPlatform(new InMemoryPlatform(static function (mixed $model, mixed $input, array $invocationOptions) use (&$options): TextResult {
            $options = $invocationOptions;

            return new TextResult('Done');
        }));

        $serverTool = ['type' => 'web_search'];
        $platform->invoke('llama3.2', 'What is the weather?', ['tools' => [
            $this->weatherTool(),
            new Tool(new ExecutionReference('Clock'), 'clock', 'Current time', null, ['category' => 'time']),
            $serverTool,
        ]])->getResult();

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string', 'description' => 'The city'],
                'commentary' => ['type' => 'string', 'description' => CommentaryPlatform::DEFAULT_DESCRIPTION],
            ],
            'required' => ['city', 'commentary'],
            'additionalProperties' => false,
        ], $options['tools'][0]->getParameters());
        $this->assertSame([
            'type' => 'object',
            'properties' => ['commentary' => ['type' => 'string', 'description' => CommentaryPlatform::DEFAULT_DESCRIPTION]],
            'required' => ['commentary'],
            'additionalProperties' => false,
        ], $options['tools'][1]->getParameters());
        $this->assertSame(['category' => 'time'], $options['tools'][1]->getMetadata());
        $this->assertSame($serverTool, $options['tools'][2]);
    }

    public function testResultWithoutToolsIsPassedAsIs()
    {
        $result = new ToolCallResult([new ToolCall('call_1', 'weather', ['commentary' => 'Not asked for.'])]);
        $platform = new CommentaryPlatform(new InMemoryPlatform(static fn (): ResultInterface => $result));

        $this->assertSame($result, $platform->invoke('llama3.2', 'What is the weather?')->getResult());
    }

    public function testCommentaryIsReportedNextToTheToolCalls()
    {
        $toolCallResult = new ToolCallResult([
            new ToolCall('call_1', 'weather', ['city' => 'Berlin', 'commentary' => 'Checking the weather in Berlin.'], 'sig'),
        ]);
        $toolCallResult->getMetadata()->add('foo', 'bar');

        $platform = new CommentaryPlatform(new InMemoryPlatform(static fn (): ResultInterface => $toolCallResult));
        $deferredResult = $platform->invoke('llama3.2', 'What is the weather?', ['tools' => [$this->weatherTool()]]);
        $result = $deferredResult->getResult();

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertEquals($this->describe([
            new CommentaryResult('Checking the weather in Berlin.'),
            new ToolCallResult([new ToolCall('call_1', 'weather', ['city' => 'Berlin'], 'sig')]),
        ]), $this->describe($result->getContent()));
        $this->assertSame('bar', $result->getMetadata()->get('foo'));
        $this->assertSame($deferredResult->getRawResult(), $result->getRawResult());
    }

    public function testToolCallWithoutCommentaryStaysAToolCallResult()
    {
        $platform = new CommentaryPlatform(new InMemoryPlatform(static fn (): ResultInterface => new ToolCallResult([
            new ToolCall('call_1', 'weather', ['city' => 'Berlin', 'commentary' => '  ']),
        ])));

        $result = $platform->invoke('llama3.2', 'What is the weather?', ['tools' => [$this->weatherTool()]])->getResult();

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertEquals([new ToolCall('call_1', 'weather', ['city' => 'Berlin'])], $result->getContent());
    }

    public function testCommentaryIsPlacedRightBeforeTheToolCallsOfAMultiPartResult()
    {
        $platform = new CommentaryPlatform(new InMemoryPlatform(static fn (): ResultInterface => new MultiPartResult([
            new TextResult('Sure.'),
            new ToolCallResult([new ToolCall('call_1', 'weather', ['city' => 'Berlin', 'commentary' => 'Checking the weather in Berlin.'])]),
        ])));

        $result = $platform->invoke('llama3.2', 'What is the weather?', ['tools' => [$this->weatherTool()]])->getResult();

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertEquals($this->describe([
            new TextResult('Sure.'),
            new CommentaryResult('Checking the weather in Berlin.'),
            new ToolCallResult([new ToolCall('call_1', 'weather', ['city' => 'Berlin'])]),
        ]), $this->describe($result->getContent()));
    }

    public function testCommentaryIsStreamedRightBeforeTheToolCalls()
    {
        $stream = new StreamResult((static function () {
            yield new TextDelta('Sure.');
            yield new ToolCallComplete([
                new ToolCall('call_1', 'weather', ['city' => 'Berlin', 'commentary' => 'Checking the weather in Berlin.']),
                new ToolCall('call_2', 'weather', ['city' => 'Paris', 'commentary' => 'And in Paris.']),
            ]);
        })());
        $stream->getMetadata()->add('foo', 'bar');

        $platform = new CommentaryPlatform(new InMemoryPlatform(static fn (): ResultInterface => $stream));
        $result = $platform->invoke('llama3.2', 'What is the weather?', ['tools' => [$this->weatherTool()], 'stream' => true])->getResult();

        $this->assertInstanceOf(StreamResult::class, $result);
        $strippedToolCalls = [
            new ToolCall('call_1', 'weather', ['city' => 'Berlin']),
            new ToolCall('call_2', 'weather', ['city' => 'Paris']),
        ];
        $this->assertEquals([
            new TextDelta('Sure.'),
            new CommentaryStart(),
            new CommentaryDelta('Checking the weather in Berlin.'),
            new CommentaryComplete('Checking the weather in Berlin.'),
            new CommentaryStart(),
            new CommentaryDelta('And in Paris.'),
            new CommentaryComplete('And in Paris.'),
            new ToolCallComplete($strippedToolCalls),
        ], iterator_to_array($result->getContent(), false));

        $this->assertEquals(
            [new Commentary('Checking the weather in Berlin.'), new Commentary('And in Paris.'), ...$strippedToolCalls],
            \array_slice($result->getAssistantMessage()->getContent(), 1),
        );
        $this->assertSame('bar', $result->getMetadata()->get('foo'));
    }

    public function testToolWithCollidingParameterIsOfferedWithoutCommentary()
    {
        $review = new Tool(new ExecutionReference('Review'), 'review', 'Reviews code', [
            'type' => 'object',
            'properties' => ['commentary' => ['type' => 'string', 'description' => 'The review']],
            'required' => ['commentary'],
            'additionalProperties' => false,
        ]);
        $toolCall = new ToolCall('call_1', 'review', ['commentary' => 'Looks good to me.']);

        $options = [];
        $inner = new InMemoryPlatform(static function (mixed $model, mixed $input, array $invocationOptions) use (&$options, $toolCall): ResultInterface {
            $options = $invocationOptions;

            return new ToolCallResult([$toolCall]);
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Tool "{tool}" already has a parameter named "{argument}", it is offered without commentary.', ['tool' => 'review', 'argument' => 'commentary']);

        $result = (new CommentaryPlatform($inner, logger: $logger))->invoke('llama3.2', 'Review this.', ['tools' => [$review]])->getResult();

        $this->assertSame($review, $options['tools'][0]);
        // the tool's own argument must reach it
        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertSame([$toolCall], $result->getContent());
    }

    public function testCustomArgumentNameAndDescription()
    {
        $options = [];
        $platform = new CommentaryPlatform(new InMemoryPlatform(static function (mixed $model, mixed $input, array $invocationOptions) use (&$options): ResultInterface {
            $options = $invocationOptions;

            return new ToolCallResult([new ToolCall('call_1', 'weather', ['city' => 'Berlin', 'reason' => 'Checking.'])]);
        }), 'reason', 'Why?');

        $result = $platform->invoke('llama3.2', 'What is the weather?', ['tools' => [$this->weatherTool()]])->getResult();

        $this->assertSame(['type' => 'string', 'description' => 'Why?'], $options['tools'][0]->getParameters()['properties']['reason']);
        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertEquals(
            $this->describe([new CommentaryResult('Checking.'), new ToolCallResult([new ToolCall('call_1', 'weather', ['city' => 'Berlin'])])]),
            $this->describe($result->getContent()),
        );
    }

    private function weatherTool(): Tool
    {
        return new Tool(new ExecutionReference('Weather'), 'weather', 'Current weather', [
            'type' => 'object',
            'properties' => ['city' => ['type' => 'string', 'description' => 'The city']],
            'required' => ['city'],
            'additionalProperties' => false,
        ]);
    }

    /**
     * @param ResultInterface[] $parts
     *
     * @return list<array{class-string<ResultInterface>, mixed}>
     */
    private function describe(array $parts): array
    {
        return array_map(static fn (ResultInterface $part): array => [$part::class, $part->getContent()], array_values($parts));
    }
}
