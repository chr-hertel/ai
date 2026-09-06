<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\AI\Store\Bridge\Postgres\StoreFactory;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = StoreFactory::createStoreFromDbal(
    connection: DriverManager::getConnection((new DsnParser())->parse('pdo-pgsql://postgres:postgres@127.0.0.1:5432/my_database')),
    tableName: 'my_table',
);

// initialize the table
$store->setup();

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer);
