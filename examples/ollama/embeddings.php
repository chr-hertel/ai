<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Ollama\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform('http://localhost:11434', env('OLLAMA_API_KEY'), httpClient: http_client());

$response = $platform->invoke('nomic-embed-text', <<<TEXT
    Once upon a time, there was a country called Japan. It was a beautiful country with a lot of mountains and rivers.
    The people of Japan were very kind and hardworking. They loved their country very much and took care of it. The
    country was very peaceful and prosperous. The people lived happily ever after.
    TEXT);

echo 'Dimensions: '.$response->asVectors()[0]->getDimensions().\PHP_EOL;
