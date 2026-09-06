<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MongoDB\Client as MongoDbClient;
use Symfony\AI\Store\Bridge\MongoDb\Store;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = new Store(
    client: new MongoDbClient('mongodb://symfony:symfony@127.0.0.1:27017'),
    databaseName: 'my-database',
    collectionName: 'my-collection',
    indexName: 'my-index',
    vectorFieldName: 'vector',
);

$vectorizer = index_movies($store);

// initialize the index
$store->setup();

ask_about_movies($store, $vectorizer, 'Which movie fits the theme of the mafia?');
