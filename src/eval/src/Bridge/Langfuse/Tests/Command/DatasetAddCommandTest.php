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
use Symfony\AI\Eval\Bridge\Langfuse\Command\DatasetAddCommand;
use Symfony\AI\Eval\Bridge\Langfuse\LangfuseDatasetSource;
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class DatasetAddCommandTest extends TestCase
{
    public function testAddsAnItem()
    {
        $body = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = json_decode($options['body'], true);

            return new JsonMockResponse(['id' => 'item-1']);
        });

        $tester = new CommandTester(new DatasetAddCommand(new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk')));

        $this->assertSame(Command::SUCCESS, $tester->execute([
            'name' => 'support',
            '--input' => '[{"user": "Where is SO-1?"}]',
            '--expect' => '{"tools_called": ["order_lookup"]}',
            '--metadata' => '{"ticket": "SUP-311"}',
            '--label' => ['shipping'],
        ]));
        $this->assertStringContainsString('Added the item "item-1" to the dataset "support"', $tester->getDisplay());
        $this->assertSame([
            'datasetName' => 'support',
            'input' => [['role' => 'user', 'content' => 'Where is SO-1?']],
            'expectedOutput' => ['tools_called' => ['order_lookup']],
            'metadata' => ['ticket' => 'SUP-311', 'labels' => ['shipping']],
        ], $body);
    }

    public function testTakesPlainTextAsMessageAndReferenceAnswer()
    {
        $body = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = json_decode($options['body'], true);

            return new JsonMockResponse(['id' => 'item-1']);
        });

        (new CommandTester(new DatasetAddCommand(new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk'))))
            ->execute(['name' => 'support', '--input' => '[urgent] Where is SO-1?', '--expect' => 'It ships tomorrow.', '--source-trace-id' => 'trace-1']);

        $this->assertSame(['datasetName' => 'support', 'input' => '[urgent] Where is SO-1?', 'expectedOutput' => 'It ships tomorrow.', 'sourceTraceId' => 'trace-1'], $body);
    }

    public function testRejectsMetadataThatIsNoObject()
    {
        $client = new MockHttpClient(static fn (): JsonMockResponse => new JsonMockResponse(['id' => 'item-1']));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The metadata must be a JSON object.');

        (new CommandTester(new DatasetAddCommand(new LangfuseDatasetSource($client, 'https://langfuse', 'pk', 'sk'))))
            ->execute(['name' => 'support', '--input' => 'Hi', '--metadata' => '["a"]']);
    }
}
