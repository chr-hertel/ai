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
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Store\InMemoryStore;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;

final class StatefulAgentTest extends TestCase
{
    public function testItPersistsTheConversationAcrossCalls()
    {
        $store = new InMemoryStore();
        $agent = new Agent($this->platform(new TextResult('Hi Wilhelm!'), new TextResult('Your name is Wilhelm.')), 'gpt-4o', store: $store);

        $agent->call('My name is Wilhelm.')->getResult();
        $agent->call('What is my name?')->getResult();

        $messages = $store->load()->getMessages();

        $this->assertCount(4, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        $this->assertSame('My name is Wilhelm.', $messages[0]->asText());
        $this->assertInstanceOf(AssistantMessage::class, $messages[1]);
        $this->assertSame('Hi Wilhelm!', $messages[1]->asText());
        $this->assertInstanceOf(UserMessage::class, $messages[2]);
        $this->assertSame('What is my name?', $messages[2]->asText());
        $this->assertInstanceOf(AssistantMessage::class, $messages[3]);
        $this->assertSame('Your name is Wilhelm.', $messages[3]->asText());
    }

    public function testTheStoredConversationIsSentToTheModel()
    {
        $store = new InMemoryStore();
        $store->save(new MessageBag(Message::ofUser('My name is Wilhelm.')));

        $seen = null;
        $platform = new InMemoryPlatform(static function (mixed $model, mixed $input) use (&$seen): TextResult {
            $seen = $input;

            return new TextResult('Your name is Wilhelm.');
        });

        $agent = new Agent($platform, 'gpt-4o', store: $store);
        $agent->call('What is my name?')->getResult();

        $this->assertInstanceOf(MessageBag::class, $seen);
        $this->assertCount(2, $seen);

        $messages = $seen->getMessages();
        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        $this->assertSame('My name is Wilhelm.', $messages[0]->asText());
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertSame('What is my name?', $messages[1]->asText());
    }

    public function testWithoutAStoreTheAgentStaysStateless()
    {
        $seen = [];
        $platform = new InMemoryPlatform(static function (mixed $model, mixed $input) use (&$seen): TextResult {
            $seen[] = \count($input);

            return new TextResult('Hi!');
        });

        $agent = new Agent($platform, 'gpt-4o');
        $agent->call('My name is Wilhelm.')->getResult();
        $agent->call('What is my name?')->getResult();

        $this->assertSame([1, 1], $seen);
    }

    public function testDroppingTheStoreForgetsTheConversation()
    {
        $store = new InMemoryStore();
        $agent = new Agent($this->platform(new TextResult('Hi!')), 'gpt-4o', store: $store);

        $agent->call('My name is Wilhelm.')->getResult();
        $this->assertCount(2, $store->load());

        $store->drop();

        $this->assertCount(0, $store->load());
    }

    private function platform(ResultInterface ...$results): InMemoryPlatform
    {
        $invocation = 0;

        return new InMemoryPlatform(static function () use (&$invocation, $results): ResultInterface {
            return $results[$invocation++] ?? throw new \LogicException('No result configured.');
        });
    }
}
