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

use Symfony\AI\Feedback\FeedbackRecorderInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RecordFeedbackHandler
{
    public function __construct(
        private readonly FeedbackRecorderInterface $recorder,
    ) {
    }

    public function __invoke(RecordFeedback $message): void
    {
        $this->recorder->record($message->getFeedback());
    }
}
