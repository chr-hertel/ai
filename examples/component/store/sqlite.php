<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Store\Bridge\Sqlite\Store;

require_once __DIR__.'/bootstrap.php';

// initialize the store — file-based SQLite for persistence
$pdo = new PDO('sqlite:'.output_file('vectors.db'));
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$store = new Store($pdo, 'movies');
$store->setup();

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer, 'Which movie fits the theme of the mafia?');
