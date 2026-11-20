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

    /**
     * @return array{subject: array{kind: string, id: string}}
     */
    private static function traceScore(string $traceId): array
    {
        return ['subject' => ['kind' => 'trace', 'id' => $traceId]];
    }
}
