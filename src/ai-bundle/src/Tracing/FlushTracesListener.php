<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tracing;

use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface as SdkTracerProviderInterface;

/**
 * Exports the batched spans once the response is sent or the command finished, also in long-running workers.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FlushTracesListener
{
    public function __construct(
        private readonly TracerProviderInterface $tracerProvider,
    ) {
    }

    public function __invoke(): void
    {
        if ($this->tracerProvider instanceof SdkTracerProviderInterface) {
            $this->tracerProvider->forceFlush();
        }
    }
}
