<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\Platform;

use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\CompleteEvent;
use Symfony\AI\Platform\Result\Stream\ErrorEvent;

/**
 * Ends the inference span once the stream is drained, when the token usage is known.
 *
 * @internal
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InferenceStreamListener extends AbstractStreamListener
{
    public function __construct(
        private readonly InferenceSpan $span,
    ) {
    }

    public function onComplete(CompleteEvent $event): void
    {
        $this->span->completeStream($event->getResult());
    }

    public function onError(ErrorEvent $event): void
    {
        $this->span->fail($event->getError());
    }
}
