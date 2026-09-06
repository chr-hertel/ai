<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenResponses\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\CustomToolCallResult;
use Symfony\AI\Platform\Result\MultiPartResult;

require_once dirname(__DIR__, 2).'/bootstrap.php';

// A tool the model calls with free-form text rather than a JSON argument object.

$platform = Factory::createPlatform('https://api.x.ai', env('XAI_API_KEY'), http_client());

$messages = new MessageBag(Message::ofUser('What is the current sentiment on X about the Symfony framework?'));
$result = $platform->invoke('grok-4-fast', $messages, [
    'tools' => [['type' => 'x_search']],
]);

$converted = $result->getResult();
$parts = $converted instanceof MultiPartResult ? $converted->getContent() : [$converted];

foreach ($parts as $part) {
    if ($part instanceof CustomToolCallResult) {
        echo sprintf('%s(%s) [%s]', $part->getName(), $part->getInput(), $part->getStatus() ?? 'unknown').\PHP_EOL;
    }
}

echo \PHP_EOL;
echo $result->asText().\PHP_EOL;
