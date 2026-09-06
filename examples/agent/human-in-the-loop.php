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
use Symfony\AI\Agent\Execution\InteractionResponse;
use Symfony\AI\Agent\Execution\Update\Interaction;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Exception\ToolInteractionException;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

#[AsTool('book_flight', 'Books a flight for the user')]
final class BookFlight
{
    public function __invoke(string $destination): string
    {
        // The tool cannot answer on its own, so it pauses the execution and asks the user
        throw ToolInteractionException::choose(sprintf('Which seat do you want for your flight to %s?', $destination), ['window', 'aisle']);
    }
}

#[AsTool('cancel_all_flights', 'Cancels all flights of the user')]
final class CancelAllFlights
{
    public function __invoke(): string
    {
        return 'All flights have been cancelled.';
    }
}

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$toolbox = new Toolbox([new BookFlight(), new CancelAllFlights()], logger: logger());
$agent = new Agent(
    $platform,
    'gpt-4o-mini',
    toolbox: $toolbox,
    // destructive tools are gated behind an explicit human approval
    toolExecutor: new SequentialToolExecutor($toolbox, toolsRequiringApproval: ['cancel_all_flights']),
);

$result = $agent->call('Book me a flight to Berlin, and cancel all my other flights.')
    ->onInteraction(static function (Interaction $interaction): InteractionResponse {
        echo '? '.$interaction->getPrompt().\PHP_EOL;

        // a real application would ask the user here - we answer for them
        $answer = match ($interaction->getReason()) {
            Symfony\AI\Agent\Execution\InteractionReason::ToolApproval => InteractionResponse::deny(),
            default => new InteractionResponse('window'),
        };

        echo '> '.json_encode($answer->getValue()).\PHP_EOL.\PHP_EOL;

        return $answer;
    })
    ->getResult();

echo $result->getContent().\PHP_EOL;
