<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Bridge\Langfuse;

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
                str_contains($url, '/api/public/scores') => new JsonMockResponse(['data' => [
                    ['traceId' => 'aaaaaaaa1111'],
                    ['traceId' => 'aaaaaaaa1111'],
                    ['traceId' => 'bbbbbbbb2222'],
                ]]),
                str_contains($url, 'aaaaaaaa1111') => new JsonMockResponse(['input' => json_encode([
                    ['role' => 'system', 'parts' => [['type' => 'text', 'content' => 'Be nice.']]],
                    ['role' => 'user', 'parts' => [['type' => 'text', 'content' => 'Where is SO-1?']]],
                ])]),
                default => new JsonMockResponse(['input' => 'I want my money back.']),
            };
        });

        $dataset = (new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk'))->pull('support-regressions', 'thumbs', 0, labels: ['triage']);

        $this->assertCount(2, $dataset);
        [$first, $second] = $dataset->getCases();
        $this->assertSame('aaaaaaaa1111', $first->getSourceRun());
        $this->assertSame([['user' => 'Where is SO-1?']], $first->getInput());
        $this->assertSame('I want my money back.', $second->getInput());
        $this->assertSame(['triage'], $second->getLabels());
        $this->assertStringContainsString('name=thumbs', $urls[0]);
        $this->assertStringContainsString('value=0', $urls[0]);
    }
}
