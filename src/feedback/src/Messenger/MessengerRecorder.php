<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback\Messenger;

use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\FeedbackRecorderInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatches feedback to a message bus, so backends are called on a worker and never block a request.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessengerRecorder implements FeedbackRecorderInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function record(Feedback $feedback): void
    {
        $this->bus->dispatch(new RecordFeedback($feedback));
    }
}
