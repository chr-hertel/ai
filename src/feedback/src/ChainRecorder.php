<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Records feedback with every recorder, a failing recorder does not keep the others from recording.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChainRecorder implements FeedbackRecorderInterface
{
    /**
     * @param iterable<FeedbackRecorderInterface> $recorders
     */
    public function __construct(
        private readonly iterable $recorders,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function record(Feedback $feedback): void
    {
        foreach ($this->recorders as $recorder) {
            try {
                $recorder->record($feedback);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to record feedback with "{recorder}".', ['recorder' => $recorder::class, 'exception' => $e]);
            }
        }
    }
}
