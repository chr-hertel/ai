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

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformExceptionInterface;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\EmbeddingMapping;
use Symfony\AI\Store\Exception\ExceptionInterface as StoreExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;

/**
 * Embeds new entities and entities whose embedded fields changed, as part of the flush.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbeddingListener
{
    /**
     * @var array<class-string, EmbeddingMapping|null>
     */
    private array $mappings = [];

    public function __construct(
        private readonly EntityEmbedder $embedder,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $entitiesByClass = [];
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $metadata = $entityManager->getClassMetadata($entity::class);
            if (null !== $this->mapping($metadata)) {
                $entitiesByClass[$metadata->getName()][] = $entity;
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $metadata = $entityManager->getClassMetadata($entity::class);
            $mapping = $this->mapping($metadata);

            if (null !== $mapping && $mapping->isAffectedBy($unitOfWork->getEntityChangeSet($entity))) {
                $entitiesByClass[$metadata->getName()][] = $entity;
            }
        }

        foreach ($entitiesByClass as $class => $entities) {
            $metadata = $entityManager->getClassMetadata($class);

            try {
                $this->embedder->embed($entityManager, $entities);
            } catch (PlatformExceptionInterface|StoreExceptionInterface|HttpClientExceptionInterface $exception) {
                $this->clearEmbeddings($metadata, $entities, $exception);
            }

            foreach ($entities as $entity) {
                $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
            }
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<object>          $entities
     */
    private function clearEmbeddings(ClassMetadata $metadata, array $entities, PlatformExceptionInterface|StoreExceptionInterface|HttpClientExceptionInterface $exception): void
    {
        $mapping = $this->mapping($metadata);
        \assert(null !== $mapping);

        foreach ($entities as $entity) {
            $metadata->setFieldValue($entity, $mapping->getVectorField(), null);
        }

        $this->logger->warning('Embedding {count} "{class}" entities failed, they stay without embedding until indexed again: {message}', [
            'count' => \count($entities),
            'class' => $metadata->getName(),
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function mapping(ClassMetadata $metadata): ?EmbeddingMapping
    {
        if (!\array_key_exists($metadata->getName(), $this->mappings)) {
            $this->mappings[$metadata->getName()] = EmbeddingMapping::fromClassMetadata($metadata);
        }

        return $this->mappings[$metadata->getName()];
    }
}
