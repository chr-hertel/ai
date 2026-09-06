<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Store\Bridge\Sqlite\Distance;
use Symfony\AI\Store\Bridge\Sqlite\VecStore;

require_once __DIR__.'/bootstrap.php';

// Install the sqlite-vec extension first — from this directory, run:
//   curl -L https://github.com/asg017/sqlite-vec/releases/download/v0.1.9/install.sh | sh
// That drops a vec0.* loadable file next to this script. Override via SQLITE_VEC_PATH if needed.
$extensionPath = $_SERVER['SQLITE_VEC_PATH'] ?? __DIR__.'/vec0.'.(\PHP_OS_FAMILY === 'Darwin' ? 'dylib' : (\PHP_OS_FAMILY === 'Windows' ? 'dll' : 'so'));
if (!file_exists($extensionPath)) {
    skip(
        sprintf('The sqlite-vec extension was not found at "%s".', $extensionPath),
        'Install it via: curl -L https://github.com/asg017/sqlite-vec/releases/download/v0.1.9/install.sh | sh',
    );
}

$pdo = new Pdo\Sqlite('sqlite:'.output_file('vec-vectors.db'));
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->loadExtension($extensionPath);

$store = new VecStore($pdo, 'movies', Distance::Cosine, 1536);
$store->setup();

$vectorizer = index_movies($store);

ask_about_movies($store, $vectorizer, 'Which movie fits the theme of the mafia?');
