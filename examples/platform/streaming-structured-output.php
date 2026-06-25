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
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Recipe;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client(), eventDispatcher: $dispatcher);

$messages = new MessageBag(
    Message::forSystem('You are a chef. Reply with JSON only, describing a recipe with its name, a list of ingredients and a list of step-by-step instructions.'),
    Message::ofUser('Give me a simple recipe for a classic Margherita pizza.'),
);

$result = $platform->invoke('gpt-4o-mini', $messages, [
    'stream' => true,
    'response_format' => Recipe::class,
]);

// Each streamed snapshot is a progressively populated Recipe instance: the name
// appears first, then ingredients trickle in one by one, then the steps. We
// re-render the whole recipe card on every snapshot so the terminal shows it
// filling up live instead of dumping the final object at once.
foreach ($result->asStreamedObject() as $recipe) {
    /** @var Recipe $recipe */
    echo "\033[2J\033[H"; // clear screen and move the cursor to the top-left

    echo '🍕 '.($recipe->name ?? '…')."\n\n";

    echo "Ingredients\n-----------\n";
    foreach ($recipe->ingredients as $ingredient) {
        echo '  • '.$ingredient."\n";
    }

    echo "\nSteps\n-----\n";
    foreach ($recipe->steps as $index => $step) {
        echo sprintf("  %d. %s\n", $index + 1, $step);
    }
}
