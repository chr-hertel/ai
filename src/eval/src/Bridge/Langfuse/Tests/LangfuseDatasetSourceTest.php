<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Bridge\Langfuse\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Eval\Bridge\Langfuse\LangfuseDatasetSource;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class LangfuseDatasetSourceTest extends TestCase
{
    public function testNegativelyScoredTracesBecomeCases()
    {
        $urls = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$urls): JsonMockResponse {
            $urls[] = $url;

            return match (true) {
                str_contains($url, '/api/public/v3/scores') => new JsonMockResponse(['data' => [
                    ['subject' => ['kind' => 'trace', 'id' => 'aaaaaaaa1111']],
                    ['subject' => ['kind' => 'observation', 'id' => 'span-1', 'traceId' => 'aaaaaaaa1111']],
                    ['subject' => ['kind' => 'session', 'id' => 'session-1']],
                    ['subject' => ['kind' => 'trace', 'id' => 'bbbbbbbb2222']],
                ], 'meta' => ['limit' => 50]]),
                str_contains($url, 'aaaaaaaa1111') => new JsonMockResponse(['data' => [['input' => json_encode([
                    ['role' => 'system', 'parts' => [['type' => 'text', 'content' => 'Be nice.']]],
                    ['role' => 'user', 'parts' => [['type' => 'text', 'content' => 'Where is SO-1?']]],
                ])]]]),
                default => new JsonMockResponse(['data' => [['input' => 'I want my money back.']]]),
            };
        });

        $dataset = (new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk'))->pull('support-regressions', 'thumbs', false, labels: ['triage']);

        $this->assertCount(2, $dataset);
        [$first, $second] = $dataset->getCases();
        $this->assertSame('support-regressions-aaaaaaaa1111', $first->getId());
        $this->assertSame('aaaaaaaa1111', $first->getSourceRun());
        $this->assertSame([['user' => 'Where is SO-1?']], $first->getInput());
        $this->assertSame('I want my money back.', $second->getInput());
        $this->assertSame(['triage'], $second->getLabels());

        parse_str((string) parse_url($urls[0], \PHP_URL_QUERY), $scoreQuery);
        $this->assertSame(['name' => 'thumbs', 'dataType' => 'BOOLEAN', 'value' => 'false', 'fields' => 'subject', 'limit' => '50'], $scoreQuery);

        parse_str((string) parse_url($urls[1], \PHP_URL_QUERY), $observationQuery);
        $this->assertStringContainsString('/api/public/v2/observations', $urls[1]);
        $this->assertSame(['traceId' => 'aaaaaaaa1111', 'isRootObservation' => 'true', 'fields' => 'core,io', 'limit' => '1'], $observationQuery);
    }

    /**
     * @return iterable<string, array{bool|float|int|string, string, string}>
     */
    public static function provideValues(): iterable
    {
        yield 'boolean' => [true, 'BOOLEAN', 'true'];
        yield 'numeric' => [0.5, 'NUMERIC', '0.5'];
        yield 'categorical' => ['wrong-tool', 'CATEGORICAL', 'wrong-tool'];
    }

    #[DataProvider('provideValues')]
    public function testFiltersByTheDataTypeOfTheValue(bool|float|int|string $value, string $dataType, string $queryValue)
    {
        $query = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$query): JsonMockResponse {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);

            return new JsonMockResponse(['data' => [], 'meta' => ['limit' => 50]]);
        });

        (new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk'))->pull('regressions', 'score', $value, new \DateTimeImmutable('2026-11-01T00:00:00+00:00'));

        $this->assertSame($dataType, $query['dataType']);
        $this->assertSame($queryValue, $query['value']);
        $this->assertSame('2026-11-01T00:00:00+00:00', $query['fromTimestamp']);
    }

    public function testFollowsTheCursorUntilTheLimitOfTracesIsReached()
    {
        $scoreQueries = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$scoreQueries): JsonMockResponse {
            if (!str_contains($url, '/api/public/v3/scores')) {
                return new JsonMockResponse(['data' => [['input' => 'I want my money back.']]]);
            }

            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $scoreQueries[] = $query;

            return match ($query['cursor'] ?? null) {
                null => new JsonMockResponse(['data' => [self::traceScore('trace-1'), self::traceScore('trace-1')], 'meta' => ['limit' => 2, 'cursor' => 'page-2']]),
                'page-2' => new JsonMockResponse(['data' => [self::traceScore('trace-2'), self::traceScore('trace-3')], 'meta' => ['limit' => 2, 'cursor' => 'page-3']]),
                default => new JsonMockResponse(['data' => [self::traceScore('trace-4')], 'meta' => ['limit' => 2]]),
            };
        });

        $dataset = (new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk'))->pull('regressions', 'thumbs', false, limit: 2);

        $this->assertSame(['regressions-trace-1', 'regressions-trace-2'], array_map(static fn ($case): string => $case->getId(), $dataset->getCases()));
        $this->assertCount(2, $scoreQueries);
        $this->assertSame('2', $scoreQueries[1]['limit']);
        $this->assertSame('page-2', $scoreQueries[1]['cursor']);
    }

    public function testStopsWhenNoCursorIsLeft()
    {
        $pages = 0;
        $client = new MockHttpClient(static function (string $method, string $url) use (&$pages): JsonMockResponse {
            if (!str_contains($url, '/api/public/v3/scores')) {
                return new JsonMockResponse(['data' => [['input' => 'I want my money back.']]]);
            }

            ++$pages;

            // a page may be empty while a cursor is present
            return match ($pages) {
                1 => new JsonMockResponse(['data' => [], 'meta' => ['limit' => 50, 'cursor' => 'page-2']]),
                default => new JsonMockResponse(['data' => [self::traceScore('trace-2')], 'meta' => ['limit' => 50]]),
            };
        });

        $dataset = (new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk'))->pull('regressions', 'thumbs', false);

        $this->assertCount(1, $dataset);
        $this->assertSame(2, $pages);
    }

    public function testSkipsTracesWithoutRootInput()
    {
        $client = new MockHttpClient(static fn (string $method, string $url): JsonMockResponse => str_contains($url, '/api/public/v3/scores')
            ? new JsonMockResponse(['data' => [self::traceScore('trace-1')], 'meta' => ['limit' => 50]])
            : new JsonMockResponse(['data' => []]));

        $this->assertCount(0, (new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk'))->pull('regressions', 'thumbs', false));
    }

    public function testFetchesTheActiveItemsOfADataset()
    {
        $urls = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$urls): JsonMockResponse {
            $urls[] = $url;
            if (str_contains($url, '/api/public/v2/datasets/')) {
                return new JsonMockResponse(['id' => 'ds-1', 'name' => 'support/regressions', 'description' => 'Reviewed failures.']);
            }

            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);

            return match ($query['page']) {
                '1' => new JsonMockResponse(['data' => [
                    [
                        'id' => 'refund-001',
                        'status' => 'ACTIVE',
                        'input' => [['role' => 'user', 'content' => 'I want my money back for SO-10023.']],
                        'expectedOutput' => ['tools_called' => ['order_lookup', 'open_refund']],
                        'metadata' => ['labels' => ['refund'], 'fixtures' => ['open_refund' => 'RF-4711'], 'ticket' => 'SUP-311'],
                        'sourceTraceId' => '4bf92f3577b34da6a3ce929d0e0e4736',
                    ],
                    ['id' => 'archived-002', 'status' => 'ARCHIVED', 'input' => 'Old question', 'expectedOutput' => null, 'metadata' => null, 'sourceTraceId' => null],
                ], 'meta' => ['page' => 1, 'limit' => 100, 'totalItems' => 3, 'totalPages' => 2]]),
                default => new JsonMockResponse(['data' => [
                    ['id' => 'shipping-003', 'status' => 'ACTIVE', 'input' => ['question' => 'Where is SO-1?'], 'expectedOutput' => 'It ships tomorrow.', 'metadata' => null, 'sourceTraceId' => null],
                ], 'meta' => ['page' => 2, 'limit' => 100, 'totalItems' => 3, 'totalPages' => 2]]),
            };
        });

        $dataset = (new LangfuseDatasetSource($client, 'https://langfuse/', 'pk', 'sk'))->fetch('support/regressions');

        $this->assertSame('support/regressions', $dataset->getName());
        $this->assertSame('Reviewed failures.', $dataset->getDescription());
        $this->assertCount(2, $dataset);
        $this->assertCount(3, $urls);
        $this->assertSame('https://langfuse/api/public/v2/datasets/support%2Fregressions', $urls[0]);
        $this->assertStringContainsString('/api/public/dataset-items?datasetName=support/regressions&page=1&limit=100', $urls[1]);

        [$refund, $shipping] = $dataset->getCases();
        $this->assertSame('refund-001', $refund->getId());
        $this->assertSame([['user' => 'I want my money back for SO-10023.']], $refund->getInput());
        $this->assertSame(['order_lookup', 'open_refund'], $refund->expectation('tools_called'));
        $this->assertSame(['refund'], $refund->getLabels());
        $this->assertSame(['open_refund' => 'RF-4711'], $refund->getFixtures());
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $refund->getSourceRun());
        $this->assertSame(['ticket' => 'SUP-311', 'langfuse_item_id' => 'refund-001', 'langfuse_dataset_id' => 'ds-1'], $refund->getMetadata());

        $this->assertSame('{"question":"Where is SO-1?"}', $shipping->getInput());
        $this->assertSame(['expected_output' => 'It ships tomorrow.'], $shipping->getExpectations());
    }

    public function testAddsAnItem()
    {
        $request = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$request): JsonMockResponse {
            $request = ['method' => $method, 'url' => $url, 'body' => json_decode($options['body'], true)];

            return new JsonMockResponse(['id' => 'item-1', 'datasetName' => 'support-regressions']);
        });

        $id = (new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk'))->addItem(
            'support-regressions',
            [['user' => 'Where is SO-1?'], ['assistant' => 'It was delivered.'], ['user' => 'It was not.']],
            ['tools_called' => ['order_lookup']],
            ['labels' => ['shipping']],
        );

        $this->assertSame('item-1', $id);
        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://langfuse/api/public/dataset-items', $request['url']);
        $this->assertSame([
            'datasetName' => 'support-regressions',
            'input' => [
                ['role' => 'user', 'content' => 'Where is SO-1?'],
                ['role' => 'assistant', 'content' => 'It was delivered.'],
                ['role' => 'user', 'content' => 'It was not.'],
            ],
            'expectedOutput' => ['tools_called' => ['order_lookup']],
            'metadata' => ['labels' => ['shipping']],
        ], $request['body']);
    }

    public function testAddsAnItemWithTheInputOfTheSourceTrace()
    {
        $body = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            if ('GET' === $method) {
                return new JsonMockResponse(['data' => [['input' => json_encode([
                    ['role' => 'system', 'parts' => [['type' => 'text', 'content' => 'Be nice.']]],
                    ['role' => 'user', 'parts' => [['type' => 'text', 'content' => 'I want my money back.']]],
                ])]]]);
            }

            $body = json_decode($options['body'], true);

            return new JsonMockResponse(['id' => 'refund-001']);
        });

        (new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk'))->addItem('support-regressions', sourceTraceId: '4bf92f3577b34da6a3ce929d0e0e4736', id: 'refund-001');

        $this->assertSame([
            'datasetName' => 'support-regressions',
            'id' => 'refund-001',
            'input' => [['role' => 'user', 'content' => 'I want my money back.']],
            'sourceTraceId' => '4bf92f3577b34da6a3ce929d0e0e4736',
        ], $body);
    }

    public function testAnItemWithoutInputIsRejected()
    {
        $client = new MockHttpClient(static fn (): JsonMockResponse => new JsonMockResponse(['data' => []]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The trace "trace-1" has no input to take for the dataset item.');

        (new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk'))->addItem('support-regressions', sourceTraceId: 'trace-1');
    }

    public function testRejectedItemsFail()
    {
        $client = new MockHttpClient(new JsonMockResponse(['message' => 'Dataset not found'], ['http_code' => 404]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Langfuse rejected the item of dataset "unknown" with status 404');

        (new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk'))->addItem('unknown', 'Hi');
    }

    /**
     * @return array{subject: array{kind: string, id: string}}
     */
    private static function traceScore(string $traceId): array
    {
        return ['subject' => ['kind' => 'trace', 'id' => $traceId]];
    }
}
