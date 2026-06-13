<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Anthropic\Factory as AnthropicFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAiFactory;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\ModelRequirements;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Task;

require_once dirname(__DIR__).'/bootstrap.php';

// Compose several providers into a single platform; selection then spans all of them.
$platform = new Platform([
    OpenAiFactory::createProvider(env('OPENAI_API_KEY'), http_client()),
    AnthropicFactory::createProvider(env('ANTHROPIC_API_KEY'), http_client()),
]);

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Write a 2-verse poem about PHP.'),
);

// Describe what you need instead of naming a model: any model that can generate text
// and supports tool calling, regardless of provider.
$requirements = new ModelRequirements(
    tasks: [Task::TEXT_GENERATION],
    inputModalities: [Modality::TEXT],
    features: [Feature::TOOL_CALLING],
);

// Pre-flight check: would any configured model satisfy this, without invoking it?
if (!$platform->supports($requirements)) {
    echo 'No configured model can satisfy the requirements.'.\PHP_EOL;
    exit(1);
}

// Capability-based selection across providers.
$model = $platform->selectModel($requirements);
echo 'Selected model: '.$model->getName().\PHP_EOL;

$result = $platform->invoke($model, $messages);
echo $result->asText().\PHP_EOL;

// Requirements can also be inferred from the prompt content and the invocation options
// (an image part would add image input, a response format would add structured output, ...).
$inferred = ModelRequirements::fromInput($messages);
echo 'Inferred input modalities: '.implode(', ', array_map(
    static fn (Modality $modality): string => $modality->value,
    $inferred->getInputModalities(),
)).\PHP_EOL;
