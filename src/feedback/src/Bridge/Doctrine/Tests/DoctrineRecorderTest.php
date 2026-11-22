<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback\Bridge\Doctrine\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Feedback\Bridge\Doctrine\DoctrineRecorder;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\Feedback\Source;

final class DoctrineRecorderTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    public function testSetupCreatesTheTable()
    {
        $recorder = new DoctrineRecorder($this->connection);
        $recorder->setup();

        $this->assertTrue($this->connection->createSchemaManager()->tablesExist(['ai_feedback']));
    }

    public function testSetupKeepsAnExistingTable()
    {
        $recorder = new DoctrineRecorder($this->connection, 'feedback');
        $recorder->setup();
        $recorder->record(new Feedback('run-1', Signal::Thumbs, true));

        $recorder->setup();

        $this->assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM feedback'));
    }

    public function testFeedbackIsStoredAsRow()
    {
        $recorder = new DoctrineRecorder($this->connection);
        $recorder->setup();

        $recorder->record(new Feedback(
            'run-1',
            Signal::Thumbs,
            false,
            Source::User,
            messageId: 'msg-1',
            comment: 'Wrong order status.',
            metadata: ['user' => 'jane', 'channel' => null],
            createdAt: new \DateTimeImmutable('2026-11-16T10:00:00+01:00'),
            traceId: '4bf92f3577b34da6a3ce929d0e0e4736',
            id: 'client-feedback-1',
        ));

        $row = $this->connection->fetchAssociative('SELECT * FROM ai_feedback');

        $this->assertIsArray($row);
        $this->assertSame('client-feedback-1', $row['id']);
        $this->assertSame('run-1', $row['run_id']);
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $row['trace_id']);
        $this->assertSame('msg-1', $row['message_id']);
        $this->assertSame('thumbs', $row['name']);
        $this->assertSame('thumbs', $row['signal_type']);
        $this->assertFalse(json_decode($row['value'], true));
        $this->assertSame('user', $row['source']);
        $this->assertSame('Wrong order status.', $row['comment']);
        $this->assertSame(['user' => 'jane', 'channel' => null], json_decode($row['metadata'], true));
        $this->assertStringStartsWith('2026-11-16 10:00:00', $row['created_at']);
    }

    public function testValuesKeepTheirType()
    {
        $recorder = new DoctrineRecorder($this->connection);
        $recorder->setup();

        $recorder->record(new Feedback('run-1', Signal::EvaluatorScore, 0.8, Source::Evaluator, 'llm_judge'));
        $recorder->record(new Feedback('run-2', Signal::Guardrail, 'denied', Source::Application));
        $recorder->record(new Feedback('run-3', Signal::Regenerated));

        $values = $this->connection->fetchAllKeyValue('SELECT run_id, value FROM ai_feedback ORDER BY run_id');

        $this->assertSame(0.8, json_decode($values['run-1'], true));
        $this->assertSame('denied', json_decode($values['run-2'], true));
        $this->assertNull($values['run-3']);
    }

    public function testRetriesAreStoredOnce()
    {
        $recorder = new DoctrineRecorder($this->connection);
        $recorder->setup();
        $createdAt = new \DateTimeImmutable('2026-11-16T10:00:00.123+00:00');

        $recorder->record(new Feedback('run-1', Signal::Thumbs, true, createdAt: $createdAt));
        $recorder->record(new Feedback('run-1', Signal::Thumbs, true, createdAt: $createdAt));

        $this->assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM ai_feedback'));
    }

    public function testFeedbackSentAgainUpdatesTheRow()
    {
        $recorder = new DoctrineRecorder($this->connection);
        $recorder->setup();

        $recorder->record(new Feedback('run-1', Signal::Thumbs, true, id: 'client-feedback-1'));
        $recorder->record(new Feedback('run-1', Signal::Thumbs, false, comment: 'Changed my mind.', id: 'client-feedback-1'));

        $rows = $this->connection->fetchAllAssociative('SELECT value, comment FROM ai_feedback');

        $this->assertCount(1, $rows);
        $this->assertFalse(json_decode($rows[0]['value'], true));
        $this->assertSame('Changed my mind.', $rows[0]['comment']);
    }
}
