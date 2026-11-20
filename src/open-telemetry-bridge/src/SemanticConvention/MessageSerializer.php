<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\SemanticConvention;

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Serializes messages into the JSON structure of gen_ai.input.messages and gen_ai.output.messages.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-type Part array{type: string, content?: string, id?: string, name?: string, arguments?: array<string, mixed>, response?: string}
 * @phpstan-type SerializedMessage array{role: string, parts: list<Part>, finish_reason?: string}
 */
final class MessageSerializer
{
    public static function systemInstructions(MessageBag $messages): ?string
    {
        $system = $messages->getSystemMessage();

        if (null === $system) {
            return null;
        }

        return self::encode([['type' => 'text', 'content' => (string) $system->getContent()]]);
    }

    public static function inputMessages(MessageBag $messages): string
    {
        $serialized = [];
        foreach ($messages->getMessages() as $message) {
            if ($message instanceof SystemMessage) {
                continue;
            }

            $serialized[] = self::message($message);
        }

        return self::encode($serialized);
    }

    public static function outputMessages(AssistantMessage $message, ?string $finishReason): string
    {
        $serialized = self::message($message);

        if (null !== $finishReason) {
            $serialized['finish_reason'] = $finishReason;
        }

        return self::encode([$serialized]);
    }

    /**
     * @param array<mixed> $value
     */
    public static function encode(array $value): string
    {
        return json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '[]';
    }

    /**
     * @return SerializedMessage
     */
    private static function message(MessageInterface $message): array
    {
        if ($message instanceof ToolCallMessage) {
            return [
                'role' => 'tool',
                'parts' => [[
                    'type' => 'tool_call_response',
                    'id' => $message->getToolCall()->getId(),
                    'response' => (string) $message->asText(),
                ]],
            ];
        }

        $parts = [];
        if ($message instanceof UserMessage || $message instanceof AssistantMessage) {
            foreach ($message->getContent() as $content) {
                $parts[] = self::content($content);
            }
        }

        return ['role' => $message->getRole()->value, 'parts' => $parts];
    }

    /**
     * @return Part
     */
    private static function content(ContentInterface $content): array
    {
        if ($content instanceof Text) {
            return ['type' => 'text', 'content' => $content->getText()];
        }

        if ($content instanceof ToolCall) {
            return self::toolCall($content);
        }

        if ($content instanceof Thinking) {
            return ['type' => 'reasoning', 'content' => $content->getContent()];
        }

        // Binary content is never inlined, the part only names its kind
        return ['type' => strtolower((new \ReflectionClass($content))->getShortName())];
    }

    /**
     * @return Part
     */
    private static function toolCall(ToolCall $toolCall): array
    {
        return [
            'type' => 'tool_call',
            'id' => $toolCall->getId(),
            'name' => $toolCall->getName(),
            'arguments' => $toolCall->getArguments(),
        ];
    }
}
