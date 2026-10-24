<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\EmbeddingMapping;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\IndexerInterface;

/**
 * Embeds the entities of an existing table in batches, or the entities it is given.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EntityIndexer implements IndexerInterface
{
    /**
     * @param positive-int $batchSize
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntityEmbedder $embedder,
        private readonly int $batchSize = 100,
    ) {
    }

    /**
     * @param string|object|iterable<object>                        $input   an entity class to walk its whole table, or entities to embed
     * @param array{only_missing?: bool, batch_size?: positive-int} $options
     */
    public function index(string|iterable|object $input, array $options = []): void
    {
        if (\is_string($input)) {
            $this->indexTable($input, $options['only_missing'] ?? false, $options['batch_size'] ?? $this->batchSize);

            return;
        }

        $this->embedder->embed($this->entityManager, is_iterable($input) ? $input : [$input]);
        $this->entityManager->flush();
    }

    /**
     * @param positive-int $batchSize
     */
    private function indexTable(string $entityClass, bool $onlyMissing, int $batchSize): void
    {
        if (!class_exists($entityClass)) {
            throw new InvalidArgumentException(\sprintf('Class "%s" does not exist.', $entityClass));
        }

        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $mapping = EmbeddingMapping::fromClassMetadata($metadata);

        if (null === $mapping) {
            throw new InvalidArgumentException(\sprintf('Entity "%s" has no field marked with #[Embedding].', $entityClass));
        }

        $identifierField = $metadata->getSingleIdentifierFieldName();
        $identifierType = $metadata->getTypeOfField($identifierField);
        $lastIdentifier = null;

        // Keyset pagination stays correct while embedded rows drop out of the "only missing" filter
        while (true) {
            $queryBuilder = $this->entityManager->createQueryBuilder()
                ->select('e')
                ->from($entityClass, 'e')
                ->orderBy('e.'.$identifierField, 'ASC')
                ->setMaxResults($batchSize);

            if ($onlyMissing) {
                $queryBuilder->andWhere(\sprintf('e.%s IS NULL', $mapping->getVectorField()));
            }

            if (null !== $lastIdentifier) {
                $queryBuilder
                    ->andWhere(\sprintf('e.%s > :lastIdentifier', $identifierField))
                    ->setParameter('lastIdentifier', $lastIdentifier, $identifierType);
            }

            $entities = $queryBuilder->getQuery()->getResult();

            if ([] === $entities) {
                return;
            }

            $this->embedder->embed($this->entityManager, $entities);
            $this->entityManager->flush();

            $lastIdentifier = $metadata->getIdentifierValues($entities[array_key_last($entities)])[$identifierField];

            // Keeps the identity map from growing with the whole table
            $this->entityManager->clear();
        }
    }
}
