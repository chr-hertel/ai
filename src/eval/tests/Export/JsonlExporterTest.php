<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Tests\Export;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Eval\Export\JsonlExporter;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

final class JsonlExporterTest extends TestCase
{
    public function testExportsOneConversationPerLine()
    {
        $runs = [
            new RecordedRun(new MessageBag(Message::forSystem('Be nice.'), Message::ofUser('Hi')), new TextResult('Hello!')),
            new RecordedRun(new MessageBag(Message::ofUser('Bye')), new TextResult('Goodbye!')),
        ];

        $lines = explode("\n", trim((new JsonlExporter())->export($runs)));

        $this->assertCount(2, $lines);
        $this->assertSame(['messages' => [
            ['role' => 'system', 'content' => 'Be nice.'],
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => 'Hello!'],
        ]], json_decode($lines[0], true));
    }
}
