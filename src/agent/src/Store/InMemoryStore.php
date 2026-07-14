<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Store;

use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A non-persistent {@see MessageStoreInterface} backed by an in-memory bag.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InMemoryStore implements MessageStoreInterface, ManagedStoreInterface, ResetInterface
{
    private ?MessageBag $messages = null;

    public function setup(array $options = []): void
    {
    }

    public function load(): MessageBag
    {
        return $this->messages ?? new MessageBag();
    }

    public function save(MessageBag $messages): void
    {
        $this->messages = $messages;
    }

    public function drop(): void
    {
        $this->messages = null;
    }

    public function reset(): void
    {
        $this->messages = null;
    }
}
