<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Connector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ResultInterface
{
    /**
     * Returns an array representation of the raw result data.
     *
     * @return array<string, mixed>
     */
    public function getRawData(): array;

    /**
     * Returns the raw result object.
     */
    public function getRawObject(): object;
}
