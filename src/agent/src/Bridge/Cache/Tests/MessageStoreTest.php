<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Cache\MessageStore;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallState;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Uid\Uuid;

final class MessageStoreTest extends TestCase
{
    public function testLoadReturnsAnEmptyBagWithTheGivenId()
    {
        $id = Uuid::v7();

        $messages = (new MessageStore(new ArrayAdapter()))->load($id);

        $this->assertCount(0, $messages);
        $this->assertTrue($id->equals($messages->getId()));
    }

    public function testSavedConversationsAreLoadedByTheirId()
    {
        $store = new MessageStore(new ArrayAdapter());
        $first = new MessageBag(Message::ofUser('Hello'));
        $second = new MessageBag(Message::ofUser('Bonjour'));
        $continued = $first->with(Message::ofAssistant('Hi'));

        $store->save($first);
        $store->save($second);
        $store->save($continued);

        $this->assertEquals($continued, $store->load($first->getId()));
        $this->assertEquals($second, $store->load($second->getId()));
    }

    public function testSavingDivergingMessagesConflicts()
    {
        $store = new MessageStore(new ArrayAdapter());
        $messages = new MessageBag(Message::ofUser('Hello'));
        $store->save($messages->with(Message::ofAssistant('Hi')));

        $this->expectException(ConflictException::class);

        $store->save($messages->with(Message::ofAssistant('Hey')));
    }

    public function testConversationsAreKeptUnderTheCacheKey()
    {
        $cache = new ArrayAdapter();
        $messages = new MessageBag(Message::ofUser('Hello'));

        (new MessageStore($cache, 'chat', 60))->save($messages);

        $this->assertTrue($cache->hasItem('chat.'.$messages->getId()->toRfc4122()));
    }

    public function testDeleteRemovesOnlyTheGivenConversation()
    {
        $store = new MessageStore(new ArrayAdapter());
        $first = new MessageBag(Message::ofUser('Hello'));
        $second = new MessageBag(Message::ofUser('Bonjour'));
        $store->save($first);
        $store->save($second);

        $store->delete($first->getId());

        $this->assertCount(0, $store->load($first->getId()));
        $this->assertCount(1, $store->load($second->getId()));
    }

    public function testDecidedToolCallIsSavedOverItsSuspendedCopy()
    {
        $store = new MessageStore(new ArrayAdapter());
        $toolCall = (new ToolCall('call_1', 'delete_file', ['path' => 'notes.txt']))->suspend('Needs approval.');
        $suspended = new MessageBag(Message::ofUser('Delete notes.txt'), new AssistantMessage($toolCall));
        $approved = $suspended->withToolCall($toolCall->approve());

        $store->save($suspended);
        $this->assertToolCallState(ToolCallState::Suspended, 'Needs approval.', $store->load($suspended->getId()));

        $store->save($approved);
        $store->save($approved->with(Message::ofToolCall($toolCall->approve(), 'Deleted')));

        $loaded = $store->load($suspended->getId());
        $this->assertCount(3, $loaded);
        $this->assertToolCallState(ToolCallState::Approved, null, $loaded);
    }

    public function testDecisionTakenInTheMeantimeConflicts()
    {
        $store = new MessageStore(new ArrayAdapter());
        $toolCall = (new ToolCall('call_1', 'delete_file', ['path' => 'notes.txt']))->suspend();
        $suspended = new MessageBag(Message::ofUser('Delete notes.txt'), new AssistantMessage($toolCall));

        $store->save($suspended);
        $store->save($suspended->withToolCall($toolCall->approve()));

        $this->expectException(ConflictException::class);

        $store->save($suspended->withToolCall($toolCall->reject('Keep it.')));
    }

    private function assertToolCallState(ToolCallState $state, ?string $reason, MessageBag $messages): void
    {
        $assistantMessage = $messages->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame($state, $assistantMessage->getToolCalls()[0]->getState());
        $this->assertSame($reason, $assistantMessage->getToolCalls()[0]->getReason());
    }
}
