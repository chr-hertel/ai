<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Fixtures;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('order_lookup', 'Looks up an order by its number', method: 'lookup')]
#[AsTool('open_refund', 'Opens a refund request', method: 'refund')]
final class SupportTools
{
    /**
     * @param string $orderNumber The order number
     *
     * @return array<string, string>
     */
    public function lookup(string $orderNumber): array
    {
        throw new \LogicException('The real tool must not be called in evals.');
    }

    /**
     * @param string $orderNumber The order number
     * @param string $reason      The reason of the customer
     */
    public function refund(string $orderNumber, string $reason): string
    {
        throw new \LogicException('The real tool must not be called in evals.');
    }
}
