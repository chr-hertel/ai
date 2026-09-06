<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\MiniMax\Factory;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__, 2).'/bootstrap.php';

$platform = Factory::createPlatform(env('MINI_MAX_API_KEY'), http_client());

$result = $platform->invoke('image-01', new Text('A cat on a kitchen table'), [
    'aspect_ratio' => '16:9',
]);

$result->asFile($file = output_file('text-to-image.jpg'));

echo 'Image saved to '.$file.\PHP_EOL;
