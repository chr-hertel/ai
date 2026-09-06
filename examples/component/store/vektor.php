<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Store\Bridge\Vektor\Store;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = new Store(sys_get_temp_dir());

// initialize the index
$store->setup();

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer);
