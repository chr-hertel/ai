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
use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Store\Bridge\MariaDb\Store;
use Symfony\AI\Store\Document\Vectorizer;

require_once __DIR__.'/bootstrap.php';

// initialize the store
$store = Store::fromDbal(
    connection: DriverManager::getConnection((new DsnParser())->parse('pdo-mysql://root@127.0.0.1:3309/my_database')),
    tableName: 'my_table_gemini',
    indexName: 'my_index',
);

// initialize the table
$store->setup(['dimensions' => 768]);

$platform = Factory::createPlatform(env('GEMINI_API_KEY'), http_client());
$vectorizer = index_movies($store, new Vectorizer($platform, 'gemini-embedding-001?dimensions=768&task_type=SEMANTIC_SIMILARITY', logger()));

ask_about_movies($store, $vectorizer, 'Which movie fits the theme of the mafia?', platform: $platform, model: 'gemini-2.5-flash-lite');
