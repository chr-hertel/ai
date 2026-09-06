<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Bridge\Clock\Clock;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Fixtures\EuropeanCapitalsTool;
use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;

require_once dirname(__DIR__, 2).'/bootstrap.php';

$platform = Factory::createPlatform(env('GEMINI_API_KEY'), http_client());

// The platform is invoked directly instead of via an Agent: the Agent's StreamListener consumes the
// terminal ToolCallComplete to execute the tools, so the raw stream is what shows the deltas.
$toolbox = new Toolbox([new Clock(), new EuropeanCapitalsTool()], logger: logger());

$messages = new MessageBag(Message::ofUser(<<<TXT
        What time is it right now, and which European capitals do you know about via your tools?
        Please tell me before you call tools.
    TXT));
$result = $platform->invoke('gemini-3.1-pro-preview', $messages, [
    'stream' => true, // enable streaming of response text
    'tools' => $toolbox->getTools(),
]);

foreach ($result->asStream() as $delta) {
    if ($delta instanceof TextDelta) {
        echo $delta;
        continue;
    }
    if ($delta instanceof ToolCallStart) {
        // Announced at the position the functionCall part appears in - Gemini delivers each call as one
        // complete part, so there are no ToolInputDelta chunks in between: the arguments arrive at once.
        output()->writeln(\PHP_EOL.'<info>[tool-call started: '.$delta->getName().']</info>');
        continue;
    }
    if ($delta instanceof ToolCallComplete) {
        // A single terminal completion carries all calls of the stream, including parallel ones.
        output()->writeln(\PHP_EOL.'<info>[tool-calls ready: '.count($delta->getToolCalls()).']</info>');
    }
}

echo \PHP_EOL;
