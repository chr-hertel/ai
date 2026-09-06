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
use MongoDB\Client as MongoDbClient;
use Symfony\AI\Chat\Bridge\Cache\MessageStore as CacheStore;
use Symfony\AI\Chat\Bridge\Doctrine\DoctrineDbalMessageStore;
use Symfony\AI\Chat\Bridge\Meilisearch\MessageStore as MeilisearchMessageStore;
use Symfony\AI\Chat\Bridge\MongoDb\MessageStore as MongoDbMessageStore;
use Symfony\AI\Chat\Bridge\Pogocache\MessageStore as PogocacheMessageStore;
use Symfony\AI\Chat\Bridge\Redis\MessageStore as RedisMessageStore;
use Symfony\AI\Chat\Bridge\Session\MessageStore as SessionMessageStore;
use Symfony\AI\Chat\Bridge\SurrealDb\MessageStore as SurrealDbMessageStore;
use Symfony\AI\Chat\Command\DropStoreCommand;
use Symfony\AI\Chat\Command\SetupStoreCommand;
use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
use Symfony\AI\Chat\MessageNormalizer;
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
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

require_once dirname(__DIR__, 2).'/bootstrap.php';

$factories = [
    'cache' => static fn (): CacheStore => new CacheStore(new ArrayAdapter(), cacheKey: 'symfony'),
    'doctrine' => static fn (): DoctrineDbalMessageStore => new DoctrineDbalMessageStore(
        'symfony',
        DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
    ),
    'meilisearch' => static fn (): MeilisearchMessageStore => new MeilisearchMessageStore(
        http_client(),
        'http://127.0.0.1:7700',
        'changeMe',
        new MonotonicClock(),
        'symfony',
    ),
    'memory' => static fn (): InMemoryStore => new InMemoryStore('symfony'),
    'mongodb' => static fn (): MongoDbMessageStore => new MongoDbMessageStore(
        new MongoDbClient('mongodb://symfony:symfony@127.0.0.1:27017'),
        'chat',
        'symfony',
    ),
    'pogocache' => static fn (): PogocacheMessageStore => new PogocacheMessageStore(
        http_client(),
        'http://127.0.0.1:9401',
        'symfony',
        'symfony',
    ),
    'redis' => static fn (): RedisMessageStore => new RedisMessageStore(new Redis([
        'host' => 'localhost',
        'port' => 6379,
    ]), 'symfony', new Serializer([
        new ArrayDenormalizer(),
        new MessageNormalizer(),
    ], [
        new JsonEncoder(),
    ])),
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
    new SetupStoreCommand(new ServiceLocator($factories)),
    new DropStoreCommand(new ServiceLocator($factories)),
]);

$clock = new MonotonicClock();
$consoleOutput = new ConsoleOutput();

/**
 * Runs a store command, retrying while the backing service is still coming up.
 *
 * The services are started by "docker compose up -d" right before this runs, so the first setup
 * of a store regularly races its container. Polling until it answers replaces a blind sleep: it
 * costs nothing when the services are already up, and it still waits when they are slow.
 *
 * @param array<string, bool|string> $input
 */
function run_store_command(Application $application, ConsoleOutput $output, MonotonicClock $clock, array $input): int
{
    $deadline = microtime(true) + 30.0;

    while (true) {
        try {
            $exitCode = $application->run(new ArrayInput($input), $output);

            if (0 === $exitCode || microtime(true) >= $deadline) {
                return $exitCode;
            }
        } catch (Throwable $e) {
            if (microtime(true) >= $deadline) {
                throw $e;
            }
        }

        $clock->sleep(1);
    }
}

foreach ($storesIds as $store) {
    $setupExitCode = run_store_command($application, $consoleOutput, $clock, [
        'command' => 'ai:message-store:setup',
        'store' => $store,
    ]);

    $dropExitCode = run_store_command($application, $consoleOutput, $clock, [
        'command' => 'ai:message-store:drop',
        'store' => $store,
        '--force' => true,
    ]);

    // Without this the example exits 0 no matter what the commands reported, and the CI job that
    // spins up five services to run it can never fail.
    verify(0 === $setupExitCode, sprintf('"%s" to set up cleanly, got exit code %d', $store, $setupExitCode));
    verify(0 === $dropExitCode, sprintf('"%s" to drop cleanly, got exit code %d', $store, $dropExitCode));

    output()->writeln(sprintf('<info>%s</info>: set up and dropped', $store));
}
