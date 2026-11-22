<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback\Bridge\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\FeedbackRecorderInterface;

/**
 * Stores each feedback as a row of a database table.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DoctrineRecorder implements FeedbackRecorderInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $tableName = 'ai_feedback',
    ) {
    }

    public function setup(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $schema = $schemaManager->introspectSchema();

        if ($schema->hasTable($this->tableName)) {
            return;
        }

        if (class_exists(ComparatorConfig::class)) {
            $comparator = $schemaManager->createComparator(new ComparatorConfig(false, false));
        } else {
            // Backwards compatibility for doctrine/dbal < 4.3
            $comparator = $schemaManager->createComparator();
        }

        $migrations = $this->connection->getDatabasePlatform()->getAlterSchemaSQL($comparator->compareSchemas($schema, $this->addTableToSchema($schema)));

        foreach ($migrations as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function record(Feedback $feedback): void
    {
        $createdAt = $feedback->getCreatedAt();
        $id = $feedback->getId() ?? hash('xxh128', implode("\0", [$feedback->getRunId(), $feedback->getName(), $createdAt->format('Y-m-d\TH:i:s.uP')]));

        $values = [
            'run_id' => $feedback->getRunId(),
            'trace_id' => $feedback->getTraceId(),
            'message_id' => $feedback->getMessageId(),
            'name' => $feedback->getName(),
            'signal_type' => $feedback->getSignal()->value,
            'value' => $feedback->getValue(),
            'source' => $feedback->getSource()->value,
            'comment' => $feedback->getComment(),
            'metadata' => $feedback->getMetadata(),
            'created_at' => $createdAt,
        ];
        $types = [
            'value' => Types::JSON,
            'metadata' => Types::JSON,
            'created_at' => Types::DATETIMETZ_IMMUTABLE,
        ];

        try {
            $this->connection->insert($this->tableName, ['id' => $id, ...$values], $types);
        } catch (UniqueConstraintViolationException) {
            $this->connection->update($this->tableName, $values, ['id' => $id], $types);
        }
    }

    private function addTableToSchema(Schema $currentSchema): Schema
    {
        $schema = clone $currentSchema;

        $table = $schema->createTable($this->tableName);
        $table->addColumn('id', Types::STRING, ['length' => 255])->setNotnull(true);
        $table->addColumn('run_id', Types::STRING, ['length' => 255])->setNotnull(true);
        $table->addColumn('trace_id', Types::STRING, ['length' => 32])->setNotnull(false);
        $table->addColumn('message_id', Types::STRING, ['length' => 255])->setNotnull(false);
        $table->addColumn('name', Types::STRING, ['length' => 255])->setNotnull(true);
        // SIGNAL is a reserved word in MySQL
        $table->addColumn('signal_type', Types::STRING, ['length' => 32])->setNotnull(true);
        $table->addColumn('value', Types::JSON)->setNotnull(false);
        $table->addColumn('source', Types::STRING, ['length' => 32])->setNotnull(true);
        $table->addColumn('comment', Types::TEXT)->setNotnull(false);
        $table->addColumn('metadata', Types::JSON)->setNotnull(true);
        $table->addColumn('created_at', Types::DATETIMETZ_IMMUTABLE)->setNotnull(true);
        $table->addIndex(['run_id'], $this->tableName.'_run_id_idx');

        if (class_exists(PrimaryKeyConstraint::class)) {
            $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, [
                new UnqualifiedName(Identifier::unquoted('id')),
            ], true));
        } else {
            $table->setPrimaryKey(['id']);
        }

        return $schema;
    }
}
