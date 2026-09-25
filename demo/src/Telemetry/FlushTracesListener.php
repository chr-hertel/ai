<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Telemetry;

use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface as SdkTracerProviderInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Exports the batched spans once the response is sent or the command finished.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsEventListener(KernelEvents::TERMINATE)]
#[AsEventListener(ConsoleEvents::TERMINATE)]
final class FlushTracesListener
{
    public function __construct(
        #[Autowire(service: 'app.telemetry.tracer_provider')]
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
