<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\EventListener;

use OpenTelemetry\API\Trace\Span;
use Symfony\AI\Agent\Event\GuardrailTriggered;
use Symfony\AI\OpenTelemetryBridge\Guard;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\AppAttributes;

/**
 * Records every guardrail intervention as an event on the current span, e.g. the invoke_agent or execute_tool span.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class GuardrailSpanListener
{
    public function __invoke(GuardrailTriggered $event): void
    {
        Guard::run(static function () use ($event): void {
            $attributes = [
                'guardrail.name' => $event->getGuardrail(),
                'guardrail.action' => $event->getAction(),
            ];

            if (null !== $event->getReason()) {
                $attributes['guardrail.reason'] = $event->getReason();
            }

            foreach ($event->getDetails() as $key => $value) {
                if (null !== $value) {
                    $attributes['guardrail.'.$key] = $value;
                }
            }

            Span::getCurrent()->addEvent(AppAttributes::GUARDRAIL_EVENT, $attributes);
        });
    }
}
