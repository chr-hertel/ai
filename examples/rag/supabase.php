<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Ollama\Factory;
use Symfony\AI\Store\Bridge\Supabase\Store;
use Symfony\AI\Store\Document\Vectorizer;

require_once __DIR__.'/bootstrap.php';

// The Supabase table, match function and vector dimension are the ones this example expects you to
// create in your project - see the store's README for the SQL.
$store = new Store(
    httpClient: http_client(),
    endpoint: env('SUPABASE_URL'),
    apiKey: env('SUPABASE_API_KEY'),
    table: 'documents',
    vectorFieldName: 'embedding',
    vectorDimension: 768,
    functionName: 'match_documents',
);

// 768 dimensions above matches Ollama's nomic-embed-text, which this example embeds with
$platform = Factory::createPlatform('http://localhost:11434', httpClient: http_client());
$vectorizer = index_movies($store, new Vectorizer($platform, 'nomic-embed-text'));

ask_about_movies($store, $vectorizer, platform: $platform, model: 'llama3.2');
