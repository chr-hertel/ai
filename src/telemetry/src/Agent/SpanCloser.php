<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Agent;

use OpenTelemetry\API\Trace\SpanInterface;
use Symfony\AI\Telemetry\Guard;

/**
 * Ends a span once, at the latest when the execution owning it is destroyed without being consumed.
 *
 * @internal
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SpanCloser
{
    private bool $ended = false;

    public function __construct(
        private readonly SpanInterface $span,
    ) {
    }

    public function __destruct()
    {
        $this->end();
    }

    public function end(): void
    {
        if ($this->ended) {
            return;
        }

        $this->ended = true;
        Guard::run(fn () => $this->span->end());
    }
}
