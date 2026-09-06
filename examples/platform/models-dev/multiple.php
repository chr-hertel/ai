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
use Symfony\AI\Platform\Bridge\Gemini\Factory as GeminiFactory;
use Symfony\AI\Platform\Bridge\Generic\Factory as GenericFactory;
use Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog;
use Symfony\AI\Platform\Bridge\ModelsDev\ProviderRegistry;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;

require_once dirname(__DIR__, 2).'/bootstrap.php';

// Querying several providers from one catalog to compare what they offer.

$platform = new Platform([
    GenericFactory::createProvider(
        baseUrl: (new ProviderRegistry())->getApiBaseUrl('deepseek'),
        apiKey: env('DEEPSEEK_API_KEY'),
        httpClient: http_client(),
        modelCatalog: new ModelCatalog('deepseek'),
        name: 'deepseek',
    ),
    AnthropicFactory::createProvider(env('ANTHROPIC_API_KEY'), http_client(), new ModelCatalog('anthropic')),
    GeminiFactory::createProvider(env('GEMINI_API_KEY'), http_client(), new ModelCatalog('google')),
]);

$messages = new MessageBag(
    Message::ofUser('Say "Hello from [provider name]" in one sentence.'),
);

echo "deepseek-chat:\n";
echo $platform->invoke('deepseek-chat', $messages)->asText()."\n\n";

echo "claude-haiku-4-5:\n";
echo $platform->invoke('claude-haiku-4-5', $messages)->asText()."\n\n";

echo "gemini-2.5-flash:\n";
echo $platform->invoke('gemini-2.5-flash', $messages)->asText()."\n\n";
