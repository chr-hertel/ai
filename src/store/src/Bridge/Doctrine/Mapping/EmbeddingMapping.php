<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\AI\Store\Bridge\Doctrine\IndexableEntityInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Where an entity keeps its embedding and what the embedding is built from.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbeddingMapping
{
    /**
     * @param ClassMetadata<object>  $metadata
     * @param non-empty-list<string> $sourceFields
     */
    private function __construct(
        private readonly ClassMetadata $metadata,
        private readonly string $vectorField,
        private readonly array $sourceFields,
    ) {
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    public static function fromClassMetadata(ClassMetadata $metadata): ?self
    {
        foreach ($metadata->getReflectionClass()->getProperties() as $property) {
            $attribute = $property->getAttributes(Embedding::class)[0] ?? null;

            if (null === $attribute) {
                continue;
            }

            $vectorField = $property->getName();
            $sourceFields = $attribute->newInstance()->from;

            if (!$metadata->hasField($vectorField)) {
                throw new InvalidArgumentException(\sprintf('The embedding "%s::$%s" is not a mapped field.', $metadata->getName(), $vectorField));
            }

            if ([] === $sourceFields) {
                throw new InvalidArgumentException(\sprintf('The embedding "%s::$%s" needs the fields it is built from.', $metadata->getName(), $vectorField));
            }

            foreach ($sourceFields as $sourceField) {
                if (!$metadata->hasField($sourceField)) {
                    throw new InvalidArgumentException(\sprintf('The embedding "%s::$%s" is built from "%s", which is not a mapped field.', $metadata->getName(), $vectorField, $sourceField));
                }
            }

            return new self($metadata, $vectorField, $sourceFields);
        }

        return null;
    }

    public function getVectorField(): string
    {
        return $this->vectorField;
    }

    public function getContent(object $entity): string
    {
        if ($entity instanceof IndexableEntityInterface) {
            return $entity->getIndexableContent();
        }

        $values = [];
        foreach ($this->sourceFields as $sourceField) {
            $value = $this->metadata->getFieldValue($entity, $sourceField);

            if (\is_scalar($value) || $value instanceof \Stringable) {
                $values[] = (string) $value;
            }
        }

        return implode(\PHP_EOL, $values);
    }

    /**
     * @param array<string, mixed> $changeSet
     */
    public function isAffectedBy(array $changeSet): bool
    {
        return [] !== array_intersect_key($changeSet, array_flip($this->sourceFields));
    }
}
