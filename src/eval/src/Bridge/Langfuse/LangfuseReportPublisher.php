<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Bridge\Langfuse;

use Symfony\AI\Eval\Exception\RuntimeException;
use Symfony\AI\Eval\Report\CaseResult;
use Symfony\AI\Eval\Report\Report;
use Symfony\AI\Eval\Report\ReportPublisherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pushes the results of a run to Langfuse as one experiment (dataset run) per variant and repetition.
 *
 * Each case result becomes an experiment item trace, sent with the OpenTelemetry experiment attributes, with the
 * scores of the evaluators on its root observation. Cases fetched from a Langfuse dataset are linked to their dataset
 * item, other cases are local items identified by their case ID.
 *
 * @see https://langfuse.com/integrations/native/opentelemetry/experiments
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LangfuseReportPublisher implements ReportPublisherInterface
{
    private const BATCH_SIZE = 100;
    private const JSON_FLAGS = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $host,
        #[\SensitiveParameter] private readonly string $publicKey,
        #[\SensitiveParameter] private readonly string $secretKey,
    ) {
    }

    public function publish(Report $report): void
    {
        $groups = [];
        $repeated = false;
        foreach ($report->getResults() as $result) {
            $groups[$result->getVariant()][$result->getRepetition()][] = $result;
            $repeated = $repeated || $result->getRepetition() > 1;
        }

        $startedAt = date('Y-m-d H:i:s');
        $spans = [];
        $scores = [];
        foreach ($groups as $variant => $repetitions) {
            foreach ($repetitions as $repetition => $results) {
                $experiment = [
                    'langfuse.experiment.id' => bin2hex(random_bytes(16)),
                    'langfuse.experiment.name' => \sprintf('%s %s %s', $report->getSuite(), $variant, $startedAt).($repeated ? ' #'.$repetition : ''),
                    'langfuse.experiment.description' => \sprintf('Eval suite "%s", variant "%s"', $report->getSuite(), $variant),
                    'langfuse.experiment.metadata.suite' => $report->getSuite(),
                    'langfuse.experiment.metadata.variant' => (string) $variant,
                    'langfuse.experiment.metadata.repetition' => (string) $repetition,
                ];

                if (null !== $datasetId = $this->datasetId($results)) {
                    $experiment['langfuse.experiment.dataset.id'] = $datasetId;
                }

                foreach ($results as $result) {
                    $span = $this->span($experiment, $result);
                    $spans[] = $span;
                    $scores = [...$scores, ...$this->scores($span['traceId'], $span['spanId'], $result)];
                }
            }
        }

        foreach (array_chunk($spans, self::BATCH_SIZE) as $batch) {
            $this->sendSpans($batch);
        }

        foreach (array_chunk($scores, self::BATCH_SIZE) as $batch) {
            $this->sendScores($batch);
        }
    }

    /**
     * The Langfuse dataset of the experiment, if every case was fetched from the same one.
     *
     * @param list<CaseResult> $results
     */
    private function datasetId(array $results): ?string
    {
        $datasetIds = [];
        foreach ($results as $result) {
            $metadata = $result->getCase()->getMetadata();
            if (!\is_string($metadata[LangfuseDatasetSource::METADATA_ITEM_ID] ?? null)) {
                return null;
            }

            $datasetIds[] = $metadata[LangfuseDatasetSource::METADATA_DATASET_ID] ?? null;
        }

        $datasetIds = array_unique($datasetIds);

        return 1 === \count($datasetIds) && \is_string($datasetIds[0] ?? null) ? $datasetIds[0] : null;
    }

    /**
     * @param array<string, string> $experiment
     *
     * @return array<string, mixed>
     */
    private function span(array $experiment, CaseResult $result): array
    {
        $case = $result->getCase();
        $run = $result->getRun();
        $traceId = bin2hex(random_bytes(16));
        $spanId = bin2hex(random_bytes(8));

        $itemId = $case->getMetadata()[LangfuseDatasetSource::METADATA_ITEM_ID] ?? null;

        $attributes = [
            ...$experiment,
            'langfuse.observation.input' => json_encode($case->getInput(), self::JSON_FLAGS),
            'langfuse.experiment.item.id' => \is_string($itemId) ? $itemId : $case->getId(),
            'langfuse.experiment.item.root_observation_id' => $spanId,
            'langfuse.experiment.item.metadata.case_id' => $case->getId(),
        ];

        if (null !== $answer = $run->getAnswer()) {
            $attributes['langfuse.observation.output'] = $answer;
        }

        if ([] !== $case->getExpectations()) {
            $attributes['langfuse.experiment.item.expected_output'] = json_encode($case->getExpectations(), self::JSON_FLAGS);
        }

        // the agent's own trace, if it is traced
        if (null !== $agentTraceId = $run->getRunContext()?->getTraceId()) {
            $attributes['langfuse.experiment.item.metadata.trace_id'] = $agentTraceId;
        }

        $endTime = (int) (microtime(true) * 1e6) * 1000;

        $span = [
            'traceId' => $traceId,
            'spanId' => $spanId,
            'name' => $case->getId(),
            'kind' => 1,
            'startTimeUnixNano' => (string) ($endTime - (int) ($run->getDuration() * 1e9)),
            'endTimeUnixNano' => (string) $endTime,
            'attributes' => array_map(static fn (string $key, string $value): array => ['key' => $key, 'value' => ['stringValue' => $value]], array_keys($attributes), $attributes),
        ];

        if (null !== $error = $run->getError()) {
            $span['status'] = ['code' => 2, 'message' => \sprintf('%s: %s', $error::class, $error->getMessage())];
        }

        return $span;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scores(string $traceId, string $observationId, CaseResult $result): array
    {
        $scores = [[
            'id' => hash('xxh128', $traceId.'passed'),
            'traceId' => $traceId,
            'observationId' => $observationId,
            'name' => 'passed',
            'value' => $result->isPassed() ? 1 : 0,
            'dataType' => 'BOOLEAN',
            'comment' => $result->isPassed() ? null : implode("\n", $result->getFailureReasons()),
        ]];

        foreach ($result->getScores() as $score) {
            $scores[] = [
                'id' => hash('xxh128', $traceId.$score->getEvaluator()),
                'traceId' => $traceId,
                'observationId' => $observationId,
                'name' => $score->getEvaluator(),
                'value' => $score->getValue(),
                'dataType' => 'NUMERIC',
                'comment' => $score->getExplanation(),
                'metadata' => [...$score->getMetadata(), 'passed' => $score->isPassed()],
            ];
        }

        return array_map(static fn (array $score): array => array_filter($score, static fn (mixed $value): bool => null !== $value), $scores);
    }

    /**
     * @param list<array<string, mixed>> $spans
     */
    private function sendSpans(array $spans): void
    {
        $response = $this->httpClient->request('POST', rtrim($this->host, '/').'/api/public/otel/v1/traces', [
            'auth_basic' => [$this->publicKey, $this->secretKey],
            'headers' => ['x-langfuse-ingestion-version' => '4'],
            'json' => ['resourceSpans' => [[
                'resource' => ['attributes' => [['key' => 'service.name', 'value' => ['stringValue' => 'symfony-ai-eval']]]],
                'scopeSpans' => [['scope' => ['name' => 'symfony/ai-eval'], 'spans' => $spans]],
            ]]],
        ]);

        if ($response->getStatusCode() >= 300) {
            throw new RuntimeException(\sprintf('Langfuse rejected the experiment items with status %d: "%s"', $response->getStatusCode(), $response->getContent(false)));
        }

        $partialSuccess = $response->toArray(false)['partialSuccess'] ?? null;
        if (\is_array($partialSuccess) && 0 < (int) ($partialSuccess['rejectedSpans'] ?? 0)) {
            throw new RuntimeException(\sprintf('Langfuse rejected %d experiment items: "%s"', $partialSuccess['rejectedSpans'], $partialSuccess['errorMessage'] ?? ''));
        }
    }

    /**
     * @param list<array<string, mixed>> $scores
     */
    private function sendScores(array $scores): void
    {
        $response = $this->httpClient->request('POST', rtrim($this->host, '/').'/api/public/scores', [
            'auth_basic' => [$this->publicKey, $this->secretKey],
            'json' => $scores,
        ]);

        $status = $response->getStatusCode();
        if ($status >= 300) {
            throw new RuntimeException(\sprintf('Langfuse rejected the experiment scores with status %d: "%s"', $status, $response->getContent(false)));
        }

        // a 207 reports scores of the batch that were rejected
        if (207 === $status && 0 < ($response->toArray(false)['rejected'] ?? 0)) {
            throw new RuntimeException(\sprintf('Langfuse rejected experiment scores: "%s"', $response->getContent(false)));
        }
    }
}
