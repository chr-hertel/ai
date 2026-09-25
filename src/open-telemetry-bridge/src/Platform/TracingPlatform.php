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
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use Symfony\AI\OpenTelemetryBridge\Guard;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\GenAiAttributes;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\MessageSerializer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Produces one CLIENT span per model invocation, from the invocation until the result is available.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TracingPlatform implements PlatformInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly TracerInterface $tracer,
        private readonly string $providerName,
        private readonly bool $captureContent = false,
    ) {
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        $modelName = $model instanceof Model ? $model->getName() : $model;
        $operation = $input instanceof MessageBag ? GenAiAttributes::OPERATION_CHAT : null;
        $span = Guard::run(fn () => $this->startSpan($modelName, $operation, $input, $options));

        if (null === $span) {
            return $this->platform->invoke($model, $input, $options);
        }

        $inferenceSpan = new InferenceSpan($span, $modelName, $operation, $this->captureContent);

        try {
            // Active while the request is built and sent, so the HTTP client span nests below
            $result = Guard::inContext($span->storeInContext(Context::getCurrent()), fn () => $this->platform->invoke($model, $input, $options));
        } catch (\Throwable $exception) {
            $inferenceSpan->fail($exception);

            throw $exception;
        }

        $result->onConvert(static function (ResultInterface $result) use ($inferenceSpan): ResultInterface {
            $inferenceSpan->converted($result);

            return $result;
        });
        $result->onError(static function (\Throwable $exception) use ($inferenceSpan): void {
            $inferenceSpan->fail($exception);
        });

        return $result;
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }

    /**
     * @param non-empty-string|null      $operation
     * @param array<mixed>|string|object $input
     * @param array<string, mixed>       $options
     */
    private function startSpan(string $modelName, ?string $operation, array|string|object $input, array $options): SpanInterface
    {
        $builder = $this->tracer->spanBuilder(GenAiAttributes::spanName($operation ?? GenAiAttributes::OPERATION_GENERATE_CONTENT, $modelName))
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(GenAiAttributes::PROVIDER_NAME, $this->providerName)
            ->setAttribute(GenAiAttributes::REQUEST_MODEL, $modelName);

        if (null !== $operation) {
            $builder->setAttribute(GenAiAttributes::OPERATION_NAME, $operation);
        }

        foreach (GenAiAttributes::REQUEST_OPTIONS as $attribute => $keys) {
            foreach ($keys as $key) {
                if (isset($options[$key]) && (\is_scalar($options[$key]) || \is_array($options[$key]))) {
                    $builder->setAttribute($attribute, \is_array($options[$key]) ? array_values($options[$key]) : $options[$key]);

                    break;
                }
            }
        }

        if ($input instanceof MessageBag) {
            $builder->setAttribute(GenAiAttributes::CONVERSATION_ID, $input->getId()->toRfc4122());
        }

        if ($this->captureContent && $input instanceof MessageBag) {
            $builder->setAttribute(GenAiAttributes::INPUT_MESSAGES, MessageSerializer::inputMessages($input));
            $builder->setAttribute(GenAiAttributes::SYSTEM_INSTRUCTIONS, MessageSerializer::systemInstructions($input));
        }

        return $builder->startSpan();
    }
}
