<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Store;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Store\InMemoryStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class InMemoryStoreTest extends TestCase
{
    public function testLoadReturnsEmptyBagByDefault()
    {
        $store = new InMemoryStore();

        $this->assertCount(0, $store->load());
    }

    public function testSaveAndLoadRoundtrip()
    {
        $store = new InMemoryStore();
        $messages = new MessageBag(Message::ofUser('Hello'));

        $store->save($messages);

        $this->assertSame($messages, $store->load());
    }

    public function testDropClearsMessages()
    {
        $store = new InMemoryStore();
        $store->save(new MessageBag(Message::ofUser('Hello')));

        $store->drop();

        $this->assertCount(0, $store->load());
    }

    public function testResetClearsMessages()
    {
        $store = new InMemoryStore();
        $store->save(new MessageBag(Message::ofUser('Hello')));

        $store->reset();

        $this->assertCount(0, $store->load());
    }
}
