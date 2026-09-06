<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\LmStudio\Factory;

require_once dirname(__DIR__, 2).'/bootstrap.php';

$platform = Factory::createPlatform('http://127.0.0.1:1234', http_client());

$result = $platform->invoke('text-embedding-nomic-embed-text-v2-moe', <<<TEXT
    Once upon a time, there was a country called Japan. It was a beautiful country with a lot of mountains and rivers.
    The people of Japan were very kind and hardworking. They loved their country very much and took care of it. The
    country was very peaceful and prosperous. The people lived happily ever after.
    TEXT);

print_vectors($result);
