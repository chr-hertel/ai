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

// Video generation is asynchronous; the bridge polls the task until the file is ready.
$result = $platform->invoke('MiniMax-Hailuo-02', new Text('A cat playing the piano on a stage, cinematic lighting'), [
    'duration' => 6,
    'resolution' => '768P',
]);

$result->asFile($file = output_file('minimax-video.mp4'));

echo 'Video saved to '.$file.\PHP_EOL;
