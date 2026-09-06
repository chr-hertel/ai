<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Store\Bridge\ClickHouse\Store;
use Symfony\Component\HttpClient\HttpClient;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = new Store(
    HttpClient::createForBaseUri('http://symfony:symfony@127.0.0.1:8123'),
    'symfony',
    'symfony',
);

// initialize the index
$store->setup();

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer);
