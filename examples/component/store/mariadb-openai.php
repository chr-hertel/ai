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
use Symfony\AI\Store\Bridge\MariaDb\Store;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = Store::fromDbal(
    connection: DriverManager::getConnection((new DsnParser())->parse('pdo-mysql://root@127.0.0.1:3309/my_database')),
    tableName: 'my_table_openai',
    indexName: 'my_index',
);

// initialize the table
$store->setup();

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer, 'Which movie fits the theme of the mafia?');
