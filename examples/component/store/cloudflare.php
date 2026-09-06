<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Store\Bridge\Cloudflare\StoreFactory;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = StoreFactory::create(
    index: 'movies',
    accountId: env('CLOUDFLARE_ACCOUNT_ID'),
    apiKey: env('CLOUDFLARE_API_KEY'),
    httpClient: http_client(),
);

// initialize the index
$store->setup();

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer);
