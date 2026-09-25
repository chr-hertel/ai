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

use OpenTelemetry\API\Trace\SpanInterface;
use Symfony\AI\OpenTelemetryBridge\Guard;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\GenAiAttributes;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\MessageSerializer;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * Owns one inference span from the invocation until the result is converted or its stream drained.
 *
 * Ends the span on destruction as a fallback, so a result that is never read or a stream that is
 * abandoned half-way does not leak an unfinished span.
 *
 * @internal
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InferenceSpan
{
    private bool $ended = false;

    public function __construct(
        private readonly SpanInterface $span,
        private readonly string $modelName,
        private readonly ?string $operation,
        private readonly bool $captureContent,
    ) {
    }

    public function __destruct()
    {
        $this->end();
    }

    public function converted(ResultInterface $result): void
    {
        if ($result instanceof StreamResult) {
            $result->addListener(new InferenceStreamListener($this));

            return;
        }

        $this->complete($result);
    }

    public function complete(ResultInterface $result): void
    {
        Guard::run(function () use ($result): void {
            if (null === $this->operation) {
                $operation = $result instanceof VectorResult ? GenAiAttributes::OPERATION_EMBEDDINGS : GenAiAttributes::OPERATION_GENERATE_CONTENT;
                $this->span->updateName(GenAiAttributes::spanName($operation, $this->modelName));
                $this->span->setAttribute(GenAiAttributes::OPERATION_NAME, $operation);
            }

            $finishReason = $this->recordMetadata($result->getMetadata());

            if ($this->captureContent) {
                $this->recordOutput($result, $finishReason);
            }
        });

        $this->end();
    }

    public function completeStream(StreamResult $result): void
    {
        Guard::run(function () use ($result): void {
            $finishReason = $this->recordMetadata($result->getMetadata());

            if ($this->captureContent) {
                $this->span->setAttribute(GenAiAttributes::OUTPUT_MESSAGES, MessageSerializer::outputMessages($result->getAssistantMessage(), $finishReason));
            }
        });

        $this->end();
    }

    public function fail(\Throwable $exception): void
    {
        Guard::recordError($this->span, $exception);
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

    private function recordMetadata(Metadata $metadata): ?string
    {
        $tokenUsage = $metadata->get('token_usage');
        if ($tokenUsage instanceof TokenUsageInterface) {
            $this->setIfKnown(GenAiAttributes::USAGE_INPUT_TOKENS, $tokenUsage->getPromptTokens());
            $this->setIfKnown(GenAiAttributes::USAGE_OUTPUT_TOKENS, $tokenUsage->getCompletionTokens());
            $this->setIfKnown(GenAiAttributes::USAGE_CACHE_READ_INPUT_TOKENS, $tokenUsage->getCacheReadTokens() ?? $tokenUsage->getCachedTokens());
            $this->setIfKnown(GenAiAttributes::USAGE_CACHE_CREATION_INPUT_TOKENS, $tokenUsage->getCacheCreationTokens());
            $this->setIfKnown(GenAiAttributes::RESPONSE_MODEL, $tokenUsage->getModel());
        }

        $finishReason = $metadata->get('finish_reason');
        if (!$finishReason instanceof FinishReason) {
            return null;
        }

        // The semconv spells the cases with underscores ("tool_call", "content_filter")
        $reason = str_replace('-', '_', $finishReason->getCase()->value);
        $this->span->setAttribute(GenAiAttributes::RESPONSE_FINISH_REASONS, [$reason]);

        return $reason;
    }

    private function recordOutput(ResultInterface $result, ?string $finishReason): void
    {
        if ($result instanceof VectorResult || $result instanceof BinaryResult) {
            return;
        }

        // The same conversion the agent uses to append the turn to the conversation
        $this->span->setAttribute(GenAiAttributes::OUTPUT_MESSAGES, MessageSerializer::outputMessages(Message::ofAssistant($result), $finishReason));
    }

    /**
     * @param non-empty-string $attribute
     */
    private function setIfKnown(string $attribute, int|string|null $value): void
    {
        if (null !== $value) {
            $this->span->setAttribute($attribute, $value);
        }
    }
}
