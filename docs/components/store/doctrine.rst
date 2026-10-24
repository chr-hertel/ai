Doctrine Store
==============

Other stores keep vectors in a storage of their own, so you have to load the matching entities
after a query. The Doctrine store keeps each vector in a field of the entity itself. Doctrine
writes it with the rest of the entity, and a query returns the entities.

The store supports PostgreSQL with the `pgvector`_ extension and SQLite with the `sqlite-vec`_
extension.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-doctrine-store

Register the ``vector`` type and the ``VECTOR_DISTANCE`` DQL function. Also map the ``vector``
database type. Without the mapping, Doctrine can't read the column when it compares the database
with your entities:

.. code-block:: yaml

    # config/packages/doctrine.yaml
    doctrine:
        dbal:
            types:
                vector: Symfony\AI\Store\Bridge\Doctrine\Dbal\Types\VectorType
            mapping_types:
                vector: vector
        orm:
            dql:
                numeric_functions:
                    VECTOR_DISTANCE: Symfony\AI\Store\Bridge\Doctrine\Query\VectorDistance

Mapping the Entity
------------------

Add a nullable ``vector`` field and mark it with the ``#[Embedding]`` attribute. The ``from``
option lists the fields the embedding is built from::

    use Doctrine\ORM\Mapping as ORM;
    use Symfony\AI\Platform\Vector\VectorInterface;
    use Symfony\AI\Store\Bridge\Doctrine\Mapping\Embedding;

    #[ORM\Entity]
    #[ORM\Index(name: 'article_embedding_idx', columns: ['embedding'])]
    class Article
    {
        // ...

        #[ORM\Column(type: 'vector', nullable: true)]
        #[Embedding(from: ['title', 'body'])]
        private ?VectorInterface $embedding = null;
    }

By default, the values of these fields are joined, one per line. To build the text in PHP, implement
:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\IndexableEntityInterface`. Its
``getIndexableContent()`` method then returns the text to embed. Keep ``from`` in sync with the
fields this method reads, because only changes of these fields embed the entity again.

The field has no size. PostgreSQL doesn't report the size of a vector column, so a size in the
mapping would make Doctrine change the column on every schema update. Give the column its size and
an HNSW index in a migration instead:

.. code-block:: sql

    -- before the table is created
    CREATE EXTENSION IF NOT EXISTS vector;

    -- after the table is created
    ALTER TABLE article ALTER COLUMN embedding TYPE vector(1536);
    DROP INDEX article_embedding_idx;
    CREATE INDEX article_embedding_idx ON article USING hnsw (embedding vector_cosine_ops);

The HNSW index keeps the name and the column of the index declared on the entity, so Doctrine sees
no difference. Use ``vector_l2_ops`` or ``vector_ip_ops`` for the euclidean and the inner product
distance.

Embedding Entities
------------------

:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\EntityEmbedder` sets the embedding field of entities.
Doctrine writes the field on the next flush, like any other field. There are two ways to call it.

To embed a table that already has rows, use
:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\EntityIndexer`::

    use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
    use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
    use Symfony\AI\Store\Document\Vectorizer;

    $embedder = new EntityEmbedder(new Vectorizer($platform, 'text-embedding-3-small'));
    $indexer = new EntityIndexer($entityManager, $embedder);

    $indexer->index(Article::class, [
        // embeds only entities without a vector, so you can run it again after an error
        'only_missing' => true,
        // 100 by default
        'batch_size' => 100,
    ]);

The indexer embeds each batch with one call to the model and flushes it. Then it clears the entity
manager, so all entities managed by it are detached. You can also pass entities to ``index()``.

To embed entities when they are created or changed, register
:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\EmbeddingListener` as a Doctrine listener of the
``onFlush`` event:

.. code-block:: yaml

    # config/services.yaml
    services:
        Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder:
            arguments: ['@ai.vectorizer.openai_embeddings']

        Symfony\AI\Store\Bridge\Doctrine\EmbeddingListener:
            tags:
                - { name: doctrine.event_listener, event: onFlush }

The listener embeds new entities. It embeds an existing entity again when one of the ``from``
fields changes. The listener calls the model during the flush, so a slow model call also slows down
the flush.

If the model call fails, the flush still saves the entities, but without embedding. The listener
logs a warning, when you pass a logger as second argument. Run the indexer with ``only_missing`` to
embed these entities later.

Querying Entities
-----------------

:class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\Store` implements the store interface. Use it with a
:class:`Symfony\\AI\\Store\\Retriever` to search entities by a text::

    use Symfony\AI\Store\Bridge\Doctrine\Dbal\Distance;
    use Symfony\AI\Store\Bridge\Doctrine\Store;
    use Symfony\AI\Store\Retriever;

    // cosine by default
    $store = new Store($entityManager, Article::class, Distance::Cosine);
    $retriever = new Retriever($store, $vectorizer);

    $documents = $retriever->retrieve('articles about cats', [
        // 5 by default
        'limit' => 5,
        // skips entities whose distance is larger
        'maxScore' => 0.5,
    ]);

    foreach ($documents as $document) {
        echo $document->getEntity()->getTitle();
    }

Each result is an :class:`Symfony\\AI\\Store\\Bridge\\Doctrine\\EntityVectorDocument` with the
entity and its distance to the query. ``add()`` and ``remove()`` set or clear the vector of the
entity with the given id and flush the entity manager. ``clear()`` clears all vectors with a bulk
update, so entities that are already loaded keep their vector until you refresh them.

To filter or join, use ``VECTOR_DISTANCE`` in your own DQL. This query finds the articles that
are most similar to ``$article``, without the article itself::

    use Symfony\AI\Store\Bridge\Doctrine\Dbal\Types\VectorType;

    $similarArticles = $entityManager->createQuery(
        'SELECT a FROM App\Entity\Article a WHERE a.id != :id ORDER BY VECTOR_DISTANCE(a.embedding, :vector) ASC'
    )
        ->setParameter('id', $article->getId())
        ->setParameter('vector', $article->getEmbedding(), VectorType::NAME)
        ->setMaxResults(5)
        ->getResult();

The third argument of ``VECTOR_DISTANCE`` selects the distance: ``'cosine'`` (default),
``'euclidean'`` or ``'inner_product'``.

Bundle Configuration
--------------------

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            doctrine:
                articles:
                    entity: 'App\Entity\Article'
                    # cosine (default), euclidean or inner_product
                    distance: 'cosine'
                    # 'doctrine.orm.entity_manager' by default
                    entity_manager: 'doctrine.orm.entity_manager'

Testing on SQLite
-----------------

On SQLite, vectors are stored as JSON text in a ``TEXT`` column. Your application can create the
schema and embed entities on a plain SQLite database, for example in a test suite. Only queries
need the sqlite-vec extension. Inner product distance isn't available on SQLite.

.. _`pgvector`: https://github.com/pgvector/pgvector
.. _`sqlite-vec`: https://github.com/asg017/sqlite-vec
