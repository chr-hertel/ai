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
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\EmbeddingMapping;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Sets the embedding field of entities, leaving the write to the unit of work.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EntityEmbedder
{
    public function __construct(
        private readonly VectorizerInterface $vectorizer,
    ) {
    }

    /**
     * @param iterable<object> $entities
     */
    public function embed(EntityManagerInterface $entityManager, iterable $entities): void
    {
        $entitiesByClass = [];
        foreach ($entities as $entity) {
            $entitiesByClass[$entityManager->getClassMetadata($entity::class)->getName()][] = $entity;
        }

        foreach ($entitiesByClass as $class => $classEntities) {
            $metadata = $entityManager->getClassMetadata($class);
            $mapping = EmbeddingMapping::fromClassMetadata($metadata);

            if (null === $mapping) {
                throw new InvalidArgumentException(\sprintf('Entity "%s" has no field marked with #[Embedding].', $class));
            }

            $contents = [];
            foreach ($classEntities as $i => $entity) {
                $content = trim($mapping->getContent($entity));

                if ('' === $content) {
                    $metadata->setFieldValue($entity, $mapping->getVectorField(), null);

                    continue;
                }

                $contents[$i] = $content;
            }

            if ([] === $contents) {
                continue;
            }

            /** @var list<VectorInterface> $vectors */
            $vectors = $this->vectorizer->vectorize(array_values($contents));

            foreach (array_keys($contents) as $position => $i) {
                $metadata->setFieldValue($classEntities[$i], $mapping->getVectorField(), $vectors[$position]);
            }
        }
    }
}
