<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\Tests\SemanticConvention;

use PHPUnit\Framework\TestCase;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\MessageSerializer;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;

final class MessageSerializerTest extends TestCase
{
    public function testInputMessagesSkipTheSystemMessage()
    {
        $toolCall = new ToolCall('call_1', 'clock', ['timezone' => 'UTC']);
        $messages = new MessageBag(
            Message::forSystem('Be brief.'),
            Message::ofUser('What time is it?', Image::fromDataUrl('data:image/png;base64,iVBORw0KGgo=')),
            Message::ofAssistant(new Thinking('Use the clock.'), $toolCall),
            Message::ofToolCall($toolCall, 'noon'),
        );

        $this->assertSame([
            ['role' => 'user', 'parts' => [['type' => 'text', 'content' => 'What time is it?'], ['type' => 'image']]],
            ['role' => 'assistant', 'parts' => [['type' => 'reasoning', 'content' => 'Use the clock.'], ['type' => 'tool_call', 'id' => 'call_1', 'name' => 'clock', 'arguments' => ['timezone' => 'UTC']]]],
            ['role' => 'tool', 'parts' => [['type' => 'tool_call_response', 'id' => 'call_1', 'response' => 'noon']]],
        ], json_decode(MessageSerializer::inputMessages($messages), true));
    }

    public function testSystemInstructions()
    {
        $this->assertSame('[{"type":"text","content":"Be brief."}]', MessageSerializer::systemInstructions(new MessageBag(Message::forSystem('Be brief.'))));
        $this->assertNull(MessageSerializer::systemInstructions(new MessageBag(Message::ofUser('Hi'))));
    }

    public function testOutputMessagesCarryTheFinishReason()
    {
        $this->assertSame(
            '[{"role":"assistant","parts":[{"type":"text","content":"Hello"}],"finish_reason":"stop"}]',
            MessageSerializer::outputMessages(Message::ofAssistant(new Text('Hello')), 'stop'),
        );
    }
}
