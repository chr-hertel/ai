<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Store\Bridge\Weaviate\StoreFactory;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = StoreFactory::create('Movies', 'http://127.0.0.1:8080', 'symfony');

// initialize the index
$store->setup();

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer);
