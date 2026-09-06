<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Cartesia\Factory;
use Symfony\AI\Platform\Message\Content\Audio;

require_once dirname(__DIR__, 2).'/bootstrap.php';

$platform = Factory::createPlatform(
    apiKey: env('CARTESIA_API_KEY'),
    version: '2025-04-16',
    httpClient: http_client(),
);

$result = $platform->invoke('ink-whisper', Audio::fromFile(dirname(__DIR__, 3).'/fixtures/audio.mp3'));

echo $result->asText().\PHP_EOL;
