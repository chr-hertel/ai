<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Store\Bridge\Redis\Store;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$redis = new Redis([
    'host' => 'localhost',
    'port' => 6379,
]);
$store = new Store(
    redis: $redis,
    indexName: 'my_index',
);

// initialize the table
$store->setup(['vector_size' => 1536]);

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer);

$store->drop();
