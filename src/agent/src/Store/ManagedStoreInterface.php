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

/**
 * A {@see MessageStoreInterface} whose backing storage has a lifecycle.
 *
 * The agent runner never calls setup() or drop(); invoking them is up to the
 * application — typically from a deployment command or an integration layer,
 * analogous to the store commands of the AI Bundle.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ManagedStoreInterface
{
    /**
     * @param array<mixed> $options
     */
    public function setup(array $options = []): void;

    public function drop(): void;
}
