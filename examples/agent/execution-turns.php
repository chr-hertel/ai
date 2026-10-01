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
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$toolbox = new Toolbox([new Clock(clock())], logger: logger());
$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox);

$messages = new MessageBag(Message::ofUser('What date and time is it? Answer in one sentence.'));
$execution = $agent->call($messages);

// each turn is one model result, with the results of the tools it requested and its own metadata
foreach ($execution->getTurns() as $i => $turn) {
    $finishReason = $turn->getMetadata()->get('finish_reason');
    $tokenUsage = $turn->getMetadata()->get('token_usage');

    echo sprintf(
        'Turn %d: %s, finish reason "%s", %s tokens',
        $i + 1,
        $turn->hasToolResults() ? 'tool call' : 'answer',
        $finishReason instanceof FinishReason ? $finishReason->getCase()->value : 'n/a',
        $tokenUsage instanceof TokenUsageInterface ? $tokenUsage->getTotalTokens() : 'n/a',
    ).\PHP_EOL;

    foreach ($turn->getToolResults() as $toolResult) {
        echo sprintf('  - %s: %s', $toolResult->getToolCall()->getName(), json_encode($toolResult->getResult())).\PHP_EOL;
    }
}

echo \PHP_EOL.$execution->asText().\PHP_EOL;
