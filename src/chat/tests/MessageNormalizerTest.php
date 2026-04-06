<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Result\ToolCallNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;

final class MessageNormalizerTest extends TestCase
{
    public function testItIsConfigured()
    {
        $normalizer = new MessageNormalizer();

        $this->assertSame([
            MessageInterface::class => true,
        ], $normalizer->getSupportedTypes(''));

        $this->assertFalse($normalizer->supportsNormalization(new \stdClass()));
        $this->assertTrue($normalizer->supportsNormalization(Message::ofUser()));

        $this->assertFalse($normalizer->supportsDenormalization('', \stdClass::class));
        $this->assertTrue($normalizer->supportsDenormalization('', MessageInterface::class));
    }

    public function testItCanNormalize()
    {
        $normalizer = new MessageNormalizer();

        $payload = $normalizer->normalize(Message::ofUser('Hello World'));

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('content', $payload);
        $this->assertArrayHasKey('contentAsBase64', $payload);
        $this->assertArrayHasKey('toolsCalls', $payload);
        $this->assertArrayHasKey('metadata', $payload);
        $this->assertArrayHasKey('addedAt', $payload);
    }

    public function testItCanDenormalize()
    {
        $uuid = Uuid::v7()->toRfc4122();
        $normalizer = new MessageNormalizer();

        $message = $normalizer->denormalize([
            'id' => $uuid,
            'type' => UserMessage::class,
            'content' => '',
            'contentAsBase64' => [
                [
                    'type' => Text::class,
                    'content' => 'What is the Symfony framework?',
                ],
            ],
            'toolsCalls' => [],
            'metadata' => [],
            'addedAt' => (new \DateTimeImmutable())->getTimestamp(),
        ], MessageInterface::class);

        $this->assertSame($uuid, $message->getId()->toRfc4122());
        $this->assertSame(Role::User, $message->getRole());
        $this->assertArrayHasKey('addedAt', $message->getMetadata()->all());
    }

    public function testItCanDenormalizeWithCustomIdentifier()
    {
        $normalizer = new MessageNormalizer();
        $message = Message::ofUser('Hello World');

        // Normalize with _id (like MongoDB)
        $payload = $normalizer->normalize($message, context: ['identifier' => '_id']);
        $this->assertArrayHasKey('_id', $payload);
        $this->assertArrayNotHasKey('id', $payload);

        $denormalized = $normalizer->denormalize($payload, MessageInterface::class, context: ['identifier' => '_id']);

        $this->assertSame($message->getId()->toRfc4122(), $denormalized->getId()->toRfc4122());
    }

    public function testItCanNormalizeAssistantMessageWithToolCalls()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new ToolCallNormalizer(),
            new MessageNormalizer(),
        ]);

        $message = new AssistantMessage('', [
            new ToolCall('call_123', 'get_weather', ['city' => 'Berlin']),
        ]);

        $payload = $serializer->normalize($message);

        $this->assertSame(AssistantMessage::class, $payload['type']);
        $this->assertCount(1, $payload['toolsCalls']);
        $this->assertSame('call_123', $payload['toolsCalls'][0]['id']);
        $this->assertSame('function', $payload['toolsCalls'][0]['type']);
        $this->assertSame('get_weather', $payload['toolsCalls'][0]['function']['name']);
        $this->assertSame('{"city":"Berlin"}', $payload['toolsCalls'][0]['function']['arguments']);

        $denormalized = $serializer->denormalize($payload, MessageInterface::class);

        $this->assertInstanceOf(AssistantMessage::class, $denormalized);
        $this->assertTrue($denormalized->hasToolCalls());
        $this->assertCount(1, $denormalized->getToolCalls());
        $this->assertSame('call_123', $denormalized->getToolCalls()[0]->getId());
        $this->assertSame('get_weather', $denormalized->getToolCalls()[0]->getName());
        $this->assertSame(['city' => 'Berlin'], $denormalized->getToolCalls()[0]->getArguments());
    }

    public function testItCanNormalizeToolCallMessage()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new ToolCallNormalizer(),
            new MessageNormalizer(),
        ]);

        $message = new ToolCallMessage(
            new ToolCall('call_456', 'search', ['query' => 'Symfony']),
            'Result content',
        );

        $payload = $serializer->normalize($message);

        $this->assertSame(ToolCallMessage::class, $payload['type']);
        $this->assertSame('Result content', $payload['content']);
        $this->assertSame('call_456', $payload['toolsCalls']['id']);
        $this->assertSame('function', $payload['toolsCalls']['type']);
        $this->assertSame('search', $payload['toolsCalls']['function']['name']);

        $denormalized = $serializer->denormalize($payload, MessageInterface::class);

        $this->assertInstanceOf(ToolCallMessage::class, $denormalized);
        $this->assertSame('call_456', $denormalized->getToolCall()->getId());
        $this->assertSame('search', $denormalized->getToolCall()->getName());
        $this->assertSame(['query' => 'Symfony'], $denormalized->getToolCall()->getArguments());
    }

    public function testItCanNormalizeAssistantMessageWithEmptyToolCallArguments()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new ToolCallNormalizer(),
            new MessageNormalizer(),
        ]);

        $message = new AssistantMessage('', [
            new ToolCall('call_789', 'list_items'),
        ]);

        $payload = $serializer->normalize($message);

        // Empty arguments must serialize to a JSON object "{}", not a JSON array "[]"
        $this->assertSame('{}', $payload['toolsCalls'][0]['function']['arguments']);
    }
}
