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
use Symfony\AI\Agent\Bridge\Ollama\Ollama;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Ollama\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__, 2).'/bootstrap.php';

$platform = Factory::createPlatform('http://localhost:11434', httpClient: http_client());

$ollama = new Ollama(http_client(), env('OLLAMA_API_KEY'));
$toolbox = new Toolbox([$ollama], logger: logger());
$agent = new Agent($platform, 'llama3.2', toolbox: $toolbox);

$result = $agent->call(new MessageBag(Message::ofUser('Retrieve the content of the ollama.com homepage')));

echo $result->asText().\PHP_EOL.\PHP_EOL;
