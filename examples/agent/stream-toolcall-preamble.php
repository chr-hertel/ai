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
use Symfony\AI\Agent\Bridge\Clock\Clock;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\ToolCall;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$toolbox = new Toolbox([new Clock(clock())], logger: logger());
$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox);

$messages = new MessageBag(
    Message::forSystem('Before calling a tool, tell the user in one short sentence what you are about to do.'),
    Message::ofUser('What date and time is it? Answer in one sentence.'),
);

foreach ($agent->call($messages, ['stream' => true])->asStream() as $delta) {
    if ($delta instanceof TextDelta) {
        echo $delta;
    }

    // the round asked for tools: the text so far was the preamble to them, the answer comes with the next round
    if ($delta instanceof ToolCallComplete) {
        $tools = array_map(static fn (ToolCall $toolCall): string => $toolCall->getName(), $delta->getToolCalls());
        output()->writeln(\PHP_EOL.'<info>[running '.implode(', ', $tools).']</info>');
    }
}

echo \PHP_EOL;
