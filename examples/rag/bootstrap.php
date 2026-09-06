<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Fixtures\Movies;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Retriever;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

/**
 * The pipeline every store example runs on top of its own store.
 *
 * Indexing the movie fixtures and asking an agent about them is identical for all of them, so it
 * lives here: what is worth reading in a store example is how that particular store is built and
 * set up, not the twenty lines of RAG wiring that follow it.
 */

/**
 * The corpus the store examples index: one text document per movie fixture.
 *
 * @return list<TextDocument>
 */
function movie_documents(): array
{
    $documents = [];

    foreach (Movies::all() as $movie) {
        $documents[] = new TextDocument(
            id: Uuid::v4(),
            content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
            metadata: new Metadata($movie),
        );
    }

    return $documents;
}

/**
 * The platform the store examples embed and chat with unless they showcase another one.
 */
function movie_platform(): PlatformInterface
{
    static $platform = null;

    return $platform ??= Factory::createPlatform(env('OPENAI_API_KEY'), http_client());
}

/**
 * Embeds the movie corpus into the given store.
 *
 * Returns the vectorizer so the example can hand the very same one to ask_about_movies() - querying
 * with a different embedding model than the one used for indexing would silently return nonsense.
 */
function index_movies(StoreInterface $store, ?Vectorizer $vectorizer = null): Vectorizer
{
    $vectorizer ??= new Vectorizer(movie_platform(), 'text-embedding-3-small', logger());

    $indexer = new DocumentIndexer(new DocumentProcessor($vectorizer, $store, logger: logger()));
    $indexer->index(movie_documents());

    return $vectorizer;
}

/**
 * Answers a question about the indexed movies, letting the agent reach the store through similarity search.
 */
function ask_about_movies(
    StoreInterface $store,
    Vectorizer $vectorizer,
    string $question = 'Which movie fits the theme of technology?',
    ?PlatformInterface $platform = null,
    string $model = 'gpt-5-mini',
): void {
    $toolbox = new Toolbox([new SimilaritySearch(new Retriever($store, $vectorizer))], logger: logger());
    $agent = new Agent($platform ?? movie_platform(), $model, toolbox: $toolbox);

    $result = $agent->call(new MessageBag(
        Message::forSystem('Please answer all user questions only using SimilaritySearch function.'),
        Message::ofUser($question),
    ));

    echo $result->asText().\PHP_EOL;
}
