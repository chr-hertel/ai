<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Bedrock\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

require_env('AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION');

$platform = Factory::createPlatform();

$messages = new MessageBag(
    Message::forSystem('You answer questions in short and concise manner.'),
    Message::ofUser('What is the Symfony framework?'),
);
$result = $platform->invoke('claude-sonnet-4-5-20250929', $messages);

echo $result->asText().\PHP_EOL;
