<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Telemetry\Bridge\Phoenix;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

/**
 * Adds "input.value" and "output.value" to agent spans, which Phoenix shows in its session view but doesn't derive
 * from "gen_ai.input.messages" and "gen_ai.output.messages", see https://github.com/Arize-ai/phoenix/issues/10622.
 *
 * @internal
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class OpenInferenceSpanExporter implements SpanExporterInterface
{
    public function __construct(
        private readonly SpanExporterInterface $exporter,
    ) {
    }

    public function export(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
    {
        $spans = [];
        foreach ($batch as $span) {
            $spans[] = $this->withOpenInference($span);
        }

        return $this->exporter->export($spans, $cancellation);
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return $this->exporter->shutdown($cancellation);
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return $this->exporter->forceFlush($cancellation);
    }

    private function withOpenInference(SpanDataInterface $span): SpanDataInterface
    {
        $attributes = $span->getAttributes();
        if ('invoke_agent' !== $attributes->get('gen_ai.operation.name')) {
            return $span;
        }

        $added = [];
        if (null !== $input = self::latestText($attributes->get('gen_ai.input.messages'), 'user')) {
            $added['input.value'] = $input;
        }
        if (null !== $output = self::latestText($attributes->get('gen_ai.output.messages'), 'assistant')) {
            $added['output.value'] = $output;
        }

        if ([] === $added) {
            return $span;
        }

        return new SpanDataWithAttributes($span, Attributes::create([...$attributes->toArray(), ...$added]));
    }

    private static function latestText(mixed $messages, string $role): ?string
    {
        if (!\is_string($messages)) {
            return null;
        }

        $decoded = json_decode($messages, true);
        if (!\is_array($decoded)) {
            return null;
        }

        foreach (array_reverse($decoded) as $message) {
            if (!\is_array($message) || $role !== ($message['role'] ?? null)) {
                continue;
            }

            $text = '';
            foreach ($message['parts'] ?? [] as $part) {
                if (\is_array($part) && 'text' === ($part['type'] ?? null)) {
                    $text .= (string) ($part['content'] ?? '');
                }
            }

            return '' === $text ? null : $text;
        }

        return null;
    }
}
