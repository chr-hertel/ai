<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Pogocache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Pogocache\MessageStore;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Serializer\MessageBagNormalizer;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;

final class MessageStoreTest extends TestCase
{
    public function testSetupRejectsOptions()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store(new MockHttpClient())->setup(['foo' => 'bar']);
    }

    public function testLoadReturnsAnEmptyBagWithTheGivenId()
    {
        $id = Uuid::v7();
        $httpClient = new MockHttpClient(static function (string $method, string $url) use ($id): MockResponse {
            self::assertSame('GET', $method);
            self::assertSame('http://127.0.0.1:9401/chat:'.$id->toRfc4122().'?auth=secret', $url);

            return new MockResponse('Not Found', ['http_code' => 404]);
        });

        $messages = $this->store($httpClient)->load($id);

        $this->assertCount(0, $messages);
        $this->assertTrue($id->equals($messages->getId()));
    }

    public function testLoadDeserializesTheStoredConversation()
    {
        $messages = new MessageBag(Message::ofUser('Hello'), Message::ofAssistant('Hi'));
        $payload = $this->serializer()->serialize($messages, 'json');

        $loaded = $this->store(new MockHttpClient([new MockResponse($payload)]))->load($messages->getId());

        $this->assertSame($payload, $this->serializer()->serialize($loaded, 'json'));
    }

    public function testNewConversationIsCreatedOnlyIfAbsentAndListed()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $id = $messages->getId()->toRfc4122();
        $httpClient = new MockHttpClient([
            new MockResponse('Not Found', ['http_code' => 404]),
            function (string $method, string $url, array $options) use ($messages, $id): MockResponse {
                $this->assertSame('PUT', $method);
                $this->assertSame('http://127.0.0.1:9401/chat:'.$id.'?auth=secret&nx=1', $url);
                $this->assertSame($this->serializer()->serialize($messages, 'json'), $options['body']);

                return new MockResponse('Stored');
            },
            new MockResponse('["other"]'),
            function (string $method, string $url, array $options) use ($id): MockResponse {
                $this->assertSame('PUT', $method);
                $this->assertSame('http://127.0.0.1:9401/chat?auth=secret', $url);
                $this->assertSame(json_encode(['other', $id]), $options['body']);

                return new MockResponse('Stored');
            },
        ]);

        $this->store($httpClient)->save($messages);

        $this->assertSame(4, $httpClient->getRequestsCount());
    }

    public function testCreatingAConversationCreatedInTheMeantimeConflicts()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Not Found', ['http_code' => 404]),
            new MockResponse('Not Found', ['http_code' => 404]),
        ]);

        $this->expectException(ConflictException::class);

        $this->store($httpClient)->save(new MessageBag(Message::ofUser('Hello')));
    }

    public function testSaveConflictsWhenTheMessagesDiverge()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $httpClient = new MockHttpClient([
            new MockResponse($this->serializer()->serialize($messages->with(Message::ofAssistant('Hi')), 'json')),
        ]);

        $this->expectException(ConflictException::class);

        $this->store($httpClient)->save($messages->with(Message::ofAssistant('Hey')));
    }

    public function testDropDeletesAllListedConversations()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $method.' '.$url;

            return new MockResponse('GET' === $method ? '["a","b"]' : 'Deleted');
        });

        $this->store($httpClient)->drop();

        $this->assertSame([
            'GET http://127.0.0.1:9401/chat?auth=secret',
            'DELETE http://127.0.0.1:9401/chat:a?auth=secret',
            'DELETE http://127.0.0.1:9401/chat:b?auth=secret',
            'DELETE http://127.0.0.1:9401/chat?auth=secret',
        ], $urls);
    }

    public function testDropWithoutConversationsSucceeds()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Not Found', ['http_code' => 404]),
            new MockResponse('Not Found', ['http_code' => 404]),
        ]);

        $this->store($httpClient)->drop();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    private function store(MockHttpClient $httpClient): MessageStore
    {
        return new MessageStore($httpClient, 'http://127.0.0.1:9401', 'secret', 'chat');
    }

    private function serializer(): Serializer
    {
        return new Serializer([new MessageBagNormalizer()], [new JsonEncoder()]);
    }
}
