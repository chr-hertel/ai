<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\Toolbox;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\OpenTelemetryBridge\Guard;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\GenAiAttributes;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\MessageSerializer;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Produces one execute_tool span per tool call.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TracingToolbox implements ToolboxInterface
{
    public function __construct(
        private readonly ToolboxInterface $toolbox,
        private readonly TracerInterface $tracer,
        private readonly bool $captureContent = false,
    ) {
    }

    public function getTools(): array
    {
        return $this->toolbox->getTools();
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        $span = Guard::run(fn () => $this->startSpan($toolCall));

        if (null === $span) {
            return $this->toolbox->execute($toolCall);
        }

        try {
            $result = Guard::inContext($span->storeInContext(Context::getCurrent()), fn () => $this->toolbox->execute($toolCall));
        } catch (\Throwable $exception) {
            Guard::recordError($span, $exception);
            Guard::run(static fn () => $span->end());

            throw $exception;
        }

        Guard::run(function () use ($span, $result): void {
            if ($this->captureContent) {
                $span->setAttribute(GenAiAttributes::TOOL_CALL_RESULT, $this->encodeResult($result->getResult()));
            }

            $span->end();
        });

        return $result;
    }

    private function startSpan(ToolCall $toolCall): SpanInterface
    {
        $builder = $this->tracer->spanBuilder(GenAiAttributes::spanName(GenAiAttributes::OPERATION_EXECUTE_TOOL, $toolCall->getName()))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute(GenAiAttributes::OPERATION_NAME, GenAiAttributes::OPERATION_EXECUTE_TOOL)
            ->setAttribute(GenAiAttributes::TOOL_NAME, $toolCall->getName())
            ->setAttribute(GenAiAttributes::TOOL_CALL_ID, $toolCall->getId())
            ->setAttribute(GenAiAttributes::TOOL_TYPE, 'function');

        if ($this->captureContent) {
            $builder->setAttribute(GenAiAttributes::TOOL_CALL_ARGUMENTS, MessageSerializer::encode($toolCall->getArguments()));
        }

        return $builder->startSpan();
    }

    private function encodeResult(mixed $result): string
    {
        if (\is_string($result)) {
            return $result;
        }

        if ($result instanceof \Stringable) {
            return (string) $result;
        }

        return json_encode($result, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '';
    }
}
