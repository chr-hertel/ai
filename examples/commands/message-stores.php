<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once dirname(__DIR__).'/bootstrap.php';

use Doctrine\DBAL\DriverManager;
use MongoDB\Client as MongoDbClient;
use Symfony\AI\Agent\Bridge\Cache\MessageStore as CacheStore;
use Symfony\AI\Agent\Bridge\Doctrine\DoctrineDbalMessageStore;
use Symfony\AI\Agent\Bridge\Meilisearch\MessageStore as MeilisearchMessageStore;
use Symfony\AI\Agent\Bridge\MongoDb\MessageStore as MongoDbMessageStore;
use Symfony\AI\Agent\Bridge\Pogocache\MessageStore as PogocacheMessageStore;
use Symfony\AI\Agent\Bridge\Redis\MessageStore as RedisMessageStore;
use Symfony\AI\Agent\Bridge\Session\MessageStore as SessionMessageStore;
use Symfony\AI\Agent\Bridge\SurrealDb\MessageStore as SurrealDbMessageStore;
use Symfony\AI\Agent\Command\DropMessageStoreCommand;
use Symfony\AI\Agent\Command\SetupMessageStoreCommand;
use Symfony\AI\Agent\MessageStore\InMemoryMessageStore;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$factories = [
    'cache' => static fn (): CacheStore => new CacheStore(new ArrayAdapter(), cacheKey: 'symfony'),
    'doctrine' => static fn (): DoctrineDbalMessageStore => new DoctrineDbalMessageStore(
        'symfony',
        DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
    ),
    'meilisearch' => static fn (): MeilisearchMessageStore => new MeilisearchMessageStore(
        http_client(),
        'http://127.0.0.1:7700',
        env('MEILISEARCH_API_KEY'),
        new MonotonicClock(),
        'symfony',
    ),
    'memory' => static fn (): InMemoryMessageStore => new InMemoryMessageStore(),
    'mongodb' => static fn (): MongoDbMessageStore => new MongoDbMessageStore(
        new MongoDbClient('mongodb://symfony:symfony@127.0.0.1:27017'),
        'chat',
        'symfony',
    ),
    'pogocache' => static fn (): PogocacheMessageStore => new PogocacheMessageStore(
        http_client(),
        'http://127.0.0.1:9401',
        env('POGOCACHE_PASSWORD'),
        'symfony',
    ),
    'redis' => static fn (): RedisMessageStore => new RedisMessageStore(new Redis([
        'host' => 'localhost',
        'port' => 6379,
    ]), 'symfony'),
    'session' => static function (): SessionMessageStore {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new SessionMessageStore($requestStack, 'symfony');
    },
    'surrealdb' => static fn (): SurrealDbMessageStore => new SurrealDbMessageStore(
        httpClient: http_client(),
        endpointUrl: 'http://127.0.0.1:8000',
        user: 'symfony',
        password: 'symfony',
        namespace: 'default',
        database: 'chat',
        table: 'chat',
    ),
];

$storesIds = array_keys($factories);

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$application->addCommands([
    new SetupMessageStoreCommand(new ServiceLocator($factories)),
    new DropMessageStoreCommand(new ServiceLocator($factories)),
]);

$clock = new MonotonicClock();
$clock->sleep(10);

foreach ($storesIds as $store) {
    $setupOutputCode = $application->run(new ArrayInput([
        'command' => 'ai:message-store:setup',
        'store' => $store,
    ]), new ConsoleOutput());

    $dropOutputCode = $application->run(new ArrayInput([
        'command' => 'ai:message-store:drop',
        'store' => $store,
        '--force' => true,
    ]), new ConsoleOutput());
}
