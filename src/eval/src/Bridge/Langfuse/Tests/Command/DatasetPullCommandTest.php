<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Bridge\Langfuse\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Eval\Bridge\Langfuse\Command\DatasetPullCommand;
use Symfony\AI\Eval\Bridge\Langfuse\LangfuseDatasetSource;
use Symfony\AI\Eval\Dataset;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class DatasetPullCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/ai-eval-pull-'.uniqid();
    }

    protected function tearDown(): void
    {
        @unlink($this->dir.'/support.yaml');
        @rmdir($this->dir);
    }

    public function testWritesTheDatasetFile()
    {
        $path = $this->dir.'/support.yaml';
        $tester = new CommandTester(new DatasetPullCommand($this->source()));

        $this->assertSame(Command::SUCCESS, $tester->execute(['name' => 'support', 'path' => $path]));
        $this->assertStringContainsString('Wrote 1 cases of the dataset "support" to '.$path, $tester->getDisplay());

        $dataset = Dataset::fromFile($path);
        $this->assertSame('support', $dataset->getName());
        $case = $dataset->getCases()[0];
        $this->assertSame('refund-001', $case->getId());
        $this->assertSame([['user' => 'I want my money back.']], $case->getInput());
        $this->assertSame(['contains' => ['RF-']], $case->getExpectations());
        $this->assertSame(['langfuse_item_id' => 'refund-001', 'langfuse_dataset_id' => 'ds-1'], $case->getMetadata());
    }

    public function testDoesNotOverwriteAnExistingFileWithoutForce()
    {
        $path = $this->dir.'/support.yaml';
        mkdir($this->dir);
        file_put_contents($path, 'curated');

        try {
            (new CommandTester(new DatasetPullCommand($this->source())))->execute(['name' => 'support', 'path' => $path]);
            $this->fail('The existing file should not have been overwritten.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('use --force to overwrite it', $e->getMessage());
        }

        $this->assertSame('curated', file_get_contents($path));

        (new CommandTester(new DatasetPullCommand($this->source())))->execute(['name' => 'support', 'path' => $path, '--force' => true]);
        $this->assertCount(1, Dataset::fromFile($path));
    }

    private function source(): LangfuseDatasetSource
    {
        $client = new MockHttpClient(static fn (string $method, string $url): JsonMockResponse => str_contains($url, '/v2/datasets/')
            ? new JsonMockResponse(['id' => 'ds-1', 'name' => 'support', 'description' => null])
            : new JsonMockResponse(['data' => [[
                'id' => 'refund-001',
                'status' => 'ACTIVE',
                'input' => [['role' => 'user', 'content' => 'I want my money back.']],
                'expectedOutput' => ['contains' => ['RF-']],
                'metadata' => null,
                'sourceTraceId' => null,
            ]], 'meta' => ['page' => 1, 'limit' => 100, 'totalItems' => 1, 'totalPages' => 1]]));

        return new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk');
    }
}
