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

/**
 * Persists the conversation of a stateful agent.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface MessageStoreInterface
{
    public function load(): MessageBag;

    public function save(MessageBag $messages): void;
}
