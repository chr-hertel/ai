<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ContextInterface;

/**
 * Keeps instrumentation failures away from the instrumented call.
 *
 * @internal
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Guard
{
    /**
     * @template T
     *
     * @param \Closure(): T $instrumentation
     *
     * @return T|null
     */
    public static function run(\Closure $instrumentation): mixed
    {
        try {
            return $instrumentation();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Runs the business call with the context active, detaching it before returning or throwing.
     *
     * @template T
     *
     * @param \Closure(): T $call
     *
     * @return T
     */
    public static function inContext(?ContextInterface $context, \Closure $call): mixed
    {
        $scope = null === $context ? null : self::run(static fn () => $context->activate());

        try {
            return $call();
        } finally {
            if (null !== $scope) {
                self::run(static fn () => $scope->detach());
            }
        }
    }

    public static function recordError(SpanInterface $span, \Throwable $exception): void
    {
        self::run(static function () use ($span, $exception): void {
            $span->recordException($exception);
            $span->setAttribute(SemanticConvention\GenAiAttributes::ERROR_TYPE, $exception::class);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        });
    }
}
