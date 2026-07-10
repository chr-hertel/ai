<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Demo;

use Symfony\AI\Platform\Capability;

/**
 * @author Symfony Community <https://symfony.com/contributors>
 */
final class Demo
{
    /**
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT];
    }
}
