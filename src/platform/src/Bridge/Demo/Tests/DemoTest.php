<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Demo\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Demo\Demo;
use Symfony\AI\Platform\Capability;

final class DemoTest extends TestCase
{
    public function testCapabilities()
    {
        $demo = new Demo();

        $this->assertContains(Capability::INPUT_TEXT, $demo->capabilities());
        $this->assertContains(Capability::OUTPUT_TEXT, $demo->capabilities());
    }
}
