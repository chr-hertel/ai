<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Contract\GeminiContract;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;

final class ToolCallStateTest extends TestCase
{
    #[DataProvider('provideDecidedToolCalls')]
    public function testTheStateOfAToolCallIsNotSentToThePlatform(ToolCall $toolCall)
    {
        $contract = GeminiContract::create();
        $model = new Model('gemini-2.5-flash');

        $this->assertSame(
            $contract->createRequestPayload($model, $this->conversation(new ToolCall('call_1', 'weather', ['city' => 'Berlin']))),
            $contract->createRequestPayload($model, $this->conversation($toolCall)),
        );
    }

    /**
     * @return iterable<string, array{ToolCall}>
     */
    public static function provideDecidedToolCalls(): iterable
    {
        $toolCall = new ToolCall('call_1', 'weather', ['city' => 'Berlin']);

        yield 'suspended' => [$toolCall->suspend('Needs approval.')];
        yield 'approved' => [$toolCall->suspend()->approve()];
        yield 'rejected' => [$toolCall->reject('Not allowed.')];
    }

    private function conversation(ToolCall $toolCall): MessageBag
    {
        return new MessageBag(
            Message::ofUser('How is the weather in Berlin?'),
            new AssistantMessage($toolCall),
            Message::ofToolCall($toolCall, 'Sunny'),
        );
    }
}
