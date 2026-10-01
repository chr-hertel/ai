<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\InMemoryRecorder;
use Symfony\AI\Feedback\Messenger\MessengerRecorder;
use Symfony\AI\Feedback\Messenger\RecordFeedback;
use Symfony\AI\Feedback\Messenger\RecordFeedbackHandler;
use Symfony\AI\Feedback\Signal;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

final class MessengerRecorderTest extends TestCase
{
    public function testFeedbackIsRecordedThroughTheBus()
    {
        $memory = new InMemoryRecorder();
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            RecordFeedback::class => [new RecordFeedbackHandler($memory)],
        ]))]);

        (new MessengerRecorder($bus))->record(new Feedback('run-1', Signal::Thumbs, true));

        $this->assertCount(1, $memory->all('run-1'));
    }
}
