<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Demo\Demo;

require_once dirname(__DIR__).'/bootstrap.php';

$demo = new Demo();

foreach ($demo->capabilities() as $capability) {
    echo $capability->value.\PHP_EOL;
}
