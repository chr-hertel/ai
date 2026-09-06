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
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\WebSearchResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('Use the web to answer the user with up-to-date information.'),
    Message::ofUser('What are the latest developments in quantum computing?'),
);

// Enable OpenAI's native, server-side web search tool just for this call.
$result = $platform->invoke('gpt-4o-mini', $messages, [
    'tools' => [
        ['type' => 'web_search'],
    ],
]);

// The web search runs server-side and is surfaced as a WebSearchResult next to the
// answer, so the result is a MultiPartResult. asText() still returns just the answer.
echo $result->asText().\PHP_EOL;

$converted = $result->getResult();
$parts = $converted instanceof MultiPartResult ? $converted->getContent() : [$converted];

foreach ($parts as $part) {
    if ($part instanceof WebSearchResult) {
        echo \PHP_EOL.'Web search query: '.($part->getQuery() ?? '(unknown)').\PHP_EOL;
    }
}
