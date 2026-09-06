<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Store\InMemory\Store as InMemoryStore;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = new InMemoryStore();

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer, 'Which movie fits the theme of the mafia?');
