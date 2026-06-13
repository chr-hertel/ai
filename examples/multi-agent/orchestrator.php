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
use Symfony\AI\Agent\Handoff\Handoff;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());
$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client(), eventDispatcher: $dispatcher);

// Specialized agent for technical questions
$technical = new Agent(
    $platform,
    name: 'technical',
    instruction: 'You are a technical support specialist. Help users resolve bugs, problems, and technical errors.',
    model: 'gpt-4o-mini?max_output_tokens=150',
);

// Orchestrator agent that routes to the technical agent when applicable and
// otherwise answers the question itself. The agent that owns the handoffs is
// its own fallback.
$orchestrator = new Agent(
    $platform,
    name: 'orchestrator',
    instruction: 'You are a helpful general assistant. Assist users with any questions or tasks they may have. You should never ever answer technical questions, delegate them to the technical agent instead.',
    handoffs: [
        new Handoff(to: $technical, description: 'bugs, problems, technical errors, programming questions'),
    ],
    model: 'gpt-5-mini',
);

echo "=== Technical Question ===\n";
$technicalQuestion = 'I get this error in my php code: "Call to undefined method App\Controller\UserController::getName()" - this is my line of code: $user->getName() where $user is an instance of User entity.';
echo "Question: $technicalQuestion\n\n";
$result = $orchestrator->call($technicalQuestion);
echo 'Answer: '.substr($result->getContent(), 0, 300).'...'.\PHP_EOL.\PHP_EOL;

echo "=== General Question ===\n";
$generalQuestion = 'Can you give me a lasagne recipe?';
echo "Question: $generalQuestion\n\n";
$result = $orchestrator->call($generalQuestion);
echo 'Answer: '.substr($result->getContent(), 0, 300).'...'.\PHP_EOL;
