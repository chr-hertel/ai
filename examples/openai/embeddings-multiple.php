<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$result = $platform->invoke('text-embedding-3-small', [
    'Once upon a time, there was a country called Japan. It was a beautiful country with a lot of mountains and rivers.',
    'The people of Japan were very kind and hardworking. They loved their country very much and took care of it.',
    'The country was very peaceful and prosperous. The people lived happily ever after.',
]);

echo 'Dimensions Text 1: '.$result->asVectors()[0]->getDimensions().\PHP_EOL;
echo 'Dimensions Text 2: '.$result->asVectors()[1]->getDimensions().\PHP_EOL;
echo 'Dimensions Text 3: '.$result->asVectors()[2]->getDimensions().\PHP_EOL;
