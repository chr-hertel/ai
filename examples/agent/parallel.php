<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$agent = new Agent($platform, 'gpt-5-mini', instruction: 'Answer in a single, short sentence.');

// callMany() drives several inputs through the same agent and keys the results by the input key
$results = $agent->callMany([
    'berlin' => 'What is the capital of Germany?',
    'paris' => 'What is the capital of France?',
    'rome' => 'What is the capital of Italy?',
])->getResults();

foreach ($results as $key => $result) {
    echo sprintf('%-8s %s', $key.':', $result->getContent()).\PHP_EOL;
}
