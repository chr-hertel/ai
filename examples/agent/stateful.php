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
use Symfony\AI\Agent\Store\InMemoryStore;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

// With a message store, the agent remembers the conversation across calls
$store = new InMemoryStore();
$agent = new Agent($platform, 'gpt-5-mini', store: $store);

echo '> My name is Wilhelm Tell.'.\PHP_EOL;
echo $agent->call('My name is Wilhelm Tell.')->getContent().\PHP_EOL.\PHP_EOL;

echo '> What is my name?'.\PHP_EOL;
echo $agent->call('What is my name?')->getContent().\PHP_EOL.\PHP_EOL;

echo sprintf('The conversation now holds %d messages.', count($store->load())).\PHP_EOL;
