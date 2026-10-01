<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Feedback\ChainRecorder;
use Symfony\AI\Feedback\Exception\InvalidArgumentException;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\FeedbackRecorderInterface;
use Symfony\AI\Feedback\InMemoryRecorder;
use Symfony\AI\Feedback\Signal;

final class FeedbackTest extends TestCase
{
    public function testNameDefaultsToTheSignal()
    {
        $this->assertSame('thumbs', (new Feedback('run-1', Signal::Thumbs, true))->getName());
        $this->assertSame('politeness', (new Feedback('run-1', Signal::EvaluatorScore, 0.9, name: 'politeness'))->getName());
    }

    public function testPositiveness()
    {
        $this->assertTrue((new Feedback('run-1', Signal::Thumbs, true))->isPositive());
        $this->assertFalse((new Feedback('run-1', Signal::Rating, 0.2))->isPositive());
        $this->assertNull((new Feedback('run-1', Signal::Correction, 'Use the order number.'))->isPositive());
    }

    public function testRunIdMustNotBeEmpty()
    {
        $this->expectException(InvalidArgumentException::class);

        new Feedback('', Signal::Thumbs, true);
    }

    public function testChainRecordsWithEveryRecorderEvenIfOneFails()
    {
        $failing = $this->createMock(FeedbackRecorderInterface::class);
        $failing->method('record')->willThrowException(new \RuntimeException('Backend down.'));
        $memory = new InMemoryRecorder();

        (new ChainRecorder([$failing, $memory]))->record(new Feedback('run-1', Signal::Thumbs, false));

        $this->assertCount(1, $memory->all('run-1'));
        $this->assertCount(0, $memory->all('run-2'));
        $this->assertCount(1, $memory->all(signal: Signal::Thumbs));
    }
}
