<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Codewithkyrian\ChromaDB\Factory as ChromaDbFactory;
use Symfony\AI\Store\Bridge\ChromaDb\Store;

require_once __DIR__.'/bootstrap.php';

// initialize the store

$store = new Store(
    (new ChromaDbFactory())
        ->withHost('http://127.0.0.1')
        ->withPort(8001)
        ->connect(),
    'movies',
);

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer);
