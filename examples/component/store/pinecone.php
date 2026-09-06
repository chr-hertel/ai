<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Probots\Pinecone\Pinecone;
use Symfony\AI\Store\Bridge\Pinecone\Store;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = new Store(Pinecone::client('pclocal', 'http://127.0.0.1:5080'), 'symfony');

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer, 'Which movie fits the theme of the mafia?');
