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
use Symfony\AI\Platform\Result\FileSearchResult;
use Symfony\AI\Platform\Result\MultiPartResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

// File search runs against an existing vector store. Create one (and upload files to it)
// via the OpenAI dashboard or API, then expose its id as OPENAI_VECTOR_STORE_ID.
$vectorStoreId = env('OPENAI_VECTOR_STORE_ID');

$messages = new MessageBag(
    Message::ofUser('Based on the indexed documents, summarize what they say about deep research.'),
);

// Enable OpenAI's native, server-side file search tool for this call.
$result = $platform->invoke('gpt-4o-mini', $messages, [
    'tools' => [
        ['type' => 'file_search', 'vector_store_ids' => [$vectorStoreId]],
    ],
]);

echo $result->asText().\PHP_EOL;

// The performed queries and the matched file chunks are surfaced as a FileSearchResult.
$converted = $result->getResult();
$parts = $converted instanceof MultiPartResult ? $converted->getContent() : [$converted];

foreach ($parts as $part) {
    if ($part instanceof FileSearchResult) {
        echo \PHP_EOL.'File search ran '.count($part->getQueries()).' query/-ies and matched '.count($part->getContent()).' chunk(s).'.\PHP_EOL;
    }
}
