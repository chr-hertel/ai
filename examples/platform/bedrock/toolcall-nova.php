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
use Symfony\AI\Agent\Bridge\Wikipedia\Wikipedia;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Bedrock\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__, 2).'/bootstrap.php';

require_env('AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION');

$platform = Factory::createPlatform();

$wikipedia = new Wikipedia(http_client());
$toolbox = new Toolbox([$wikipedia]);
$agent = new Agent($platform, 'nova-pro', toolbox: $toolbox);

$messages = new MessageBag(
    Message::ofUser('Who is the current chancellor of Germany? Use Wikipedia to find the answer.')
);
$result = $agent->call($messages);

echo $result->asText().\PHP_EOL;
