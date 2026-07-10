<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;

/**
 * @author Symfony Community <https://symfony.com/contributors>
 */
final class CrossPackageCapabilityTest extends TestCase
{
    public function testIsOutput()
    {
        $this->assertTrue(Capability::OUTPUT_TEXT->isOutput());
        $this->assertFalse(Capability::INPUT_TEXT->isOutput());
    }
}
