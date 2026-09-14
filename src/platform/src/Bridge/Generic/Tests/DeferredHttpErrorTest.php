<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\Factory;
use Symfony\AI\Platform\Event\ResultErrorEvent;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class DeferredHttpErrorTest extends TestCase
{
    public function testHttpErrorSurfacesFromGetResultInsteadOfInvoke()
    {
        $recorder = new class {
            /** @var list<object> */
            public array $events = [];

            /** @var list<\Throwable> */
            public array $errors = [];

            /**
             * @return list<ResultErrorEvent>
             */
            public function errorEvents(): array
            {
                return array_values(array_filter($this->events, static fn (object $event): bool => $event instanceof ResultErrorEvent));
            }
        };

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (object $event) use ($recorder): object {
            $recorder->events[] = $event;

            return $event;
        });

        $httpClient = new MockHttpClient(new JsonMockResponse(['error' => ['message' => 'The server is overloaded.']], ['http_code' => 500]));
        $platform = Factory::createPlatform('https://example.com', 'api-key', $httpClient, eventDispatcher: $eventDispatcher);

        $deferredResult = $platform->invoke('gpt-4o', new MessageBag(Message::ofUser('Hello')));
        $deferredResult->onError(static function (\Throwable $error) use ($recorder): void {
            $recorder->errors[] = $error;
        });

        $this->assertSame([], $recorder->errorEvents());

        try {
            $deferredResult->getResult();
            $this->fail('Expected a ServerException to be thrown.');
        } catch (ServerException $exception) {
            $this->assertSame(500, $exception->getStatusCode());
            $this->assertStringContainsString('The server is overloaded.', $exception->getMessage());
        }

        $this->assertSame([$exception], $recorder->errors);

        $errorEvents = $recorder->errorEvents();
        $this->assertCount(1, $errorEvents);
        $this->assertSame($exception, $errorEvents[0]->getError());

        try {
            $deferredResult->getResult();
            $this->fail('Expected the cached ServerException to be rethrown.');
        } catch (ServerException $rethrown) {
            $this->assertSame($exception, $rethrown);
        }
    }
}
