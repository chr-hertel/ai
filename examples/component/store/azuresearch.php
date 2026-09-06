<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Store\Bridge\AzureSearch\StoreFactory;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = StoreFactory::create(
    indexName: 'movies',
    endpoint: env('AZURE_SEARCH_ENDPOINT'),
    apiKey: env('AZURE_SEARCH_API_KEY'),
    apiVersion: '2024-07-01',
    httpClient: http_client(),
);

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer, 'Which movie fits the theme of the mafia?');
