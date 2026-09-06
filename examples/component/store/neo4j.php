<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Store\Bridge\Neo4j\Store;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = new Store(
    httpClient: http_client(),
    endpointUrl: 'http://127.0.0.1:7474',
    username: 'neo4j',
    password: 'symfonyai',
    databaseName: 'neo4j',
    vectorIndexName: 'Movies',
    nodeName: 'movies',
);

// initialize the table
$store->setup();

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer);
