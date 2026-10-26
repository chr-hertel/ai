<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Agent\MessageStore\ConflictDetector;
use Symfony\AI\Agent\MessageStore\ManagedMessageStoreInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

/**
 * Keeps each conversation in its own cache item, next to an item listing the conversation ids for dropping them.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessageStore implements ManagedMessageStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly string $cacheKey = '_message_store_cache',
        private readonly int $ttl = 86400,
    ) {
    }

    public function setup(array $options = []): void
    {
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        $item = $this->cache->getItem($this->key($id->toRfc4122()));

        return $item->isHit() ? $item->get() : (new MessageBag())->withId($id);
    }

    public function save(MessageBag $messages): void
    {
        ConflictDetector::assertContinues($this->load($messages->getId()), $messages);

        $id = $messages->getId()->toRfc4122();
        $ids = $this->ids();
        $ids[$id] = true;

        $this->cache->saveDeferred($this->cache->getItem($this->key($id))->set($messages)->expiresAfter($this->ttl));
        $this->cache->saveDeferred($this->cache->getItem($this->cacheKey)->set($ids)->expiresAfter($this->ttl));
        $this->cache->commit();
    }

    public function drop(): void
    {
        $this->cache->deleteItems([
            ...array_map($this->key(...), array_keys($this->ids())),
            $this->cacheKey,
        ]);
    }

    /**
     * @return array<string, true>
     */
    private function ids(): array
    {
        $item = $this->cache->getItem($this->cacheKey);

        return $item->isHit() ? $item->get() : [];
    }

    private function key(string $id): string
    {
        return $this->cacheKey.'.'.$id;
    }
}
