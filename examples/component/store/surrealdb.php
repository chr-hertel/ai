<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Store\Bridge\SurrealDb\StoreFactory;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = StoreFactory::create(
    namespace: 'default',
    database: 'movies',
    user: 'symfony',
    password: 'symfony',
    endpoint: 'http://127.0.0.1:8000',
    httpClient: http_client(),
    table: 'movies',
);

// initialize the table
$store->setup();

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer);
