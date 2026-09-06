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
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::ofUser('Use Python to compute the 20th Fibonacci number and show the code you ran.'),
);

// Enable OpenAI's native, server-side code interpreter for this call.
$result = $platform->invoke('gpt-4o-mini', $messages, [
    'tools' => [
        ['type' => 'code_interpreter', 'container' => ['type' => 'auto']],
    ],
]);

echo $result->asText().\PHP_EOL;

// The executed code and its output are surfaced as ExecutableCodeResult /
// CodeExecutionResult next to the answer.
$converted = $result->getResult();
$parts = $converted instanceof MultiPartResult ? $converted->getContent() : [$converted];

foreach ($parts as $part) {
    if ($part instanceof ExecutableCodeResult) {
        echo \PHP_EOL.'--- Code ('.($part->getLanguage() ?? 'unknown').') ---'.\PHP_EOL.$part->getContent().\PHP_EOL;
    }
    if ($part instanceof CodeExecutionResult) {
        echo \PHP_EOL.'--- Output ---'.\PHP_EOL.($part->getContent() ?? '(none)').\PHP_EOL;
    }
}
