<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback\Bridge\Langfuse;

use Symfony\AI\Feedback\Exception\InvalidArgumentException;
use Symfony\AI\Feedback\Exception\RuntimeException;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\FeedbackRecorderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Records feedback as a score on the Langfuse trace of the run.
 *
 * Langfuse attaches scores to traces, so only feedback carrying the trace ID of its run can be recorded. That is the
 * case when the agent is traced with the Telemetry component and Langfuse is the tracing backend.
 *
 * @see https://langfuse.com/docs/scores/custom
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LangfuseRecorder implements FeedbackRecorderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $host,
        #[\SensitiveParameter] private readonly string $publicKey,
        #[\SensitiveParameter] private readonly string $secretKey,
    ) {
    }

    public function record(Feedback $feedback): void
    {
        $response = $this->httpClient->request('POST', rtrim($this->host, '/').'/api/public/scores', [
            'auth_basic' => [$this->publicKey, $this->secretKey],
            'json' => $this->payload($feedback),
        ]);

        $status = $response->getStatusCode();
        if ($status >= 300) {
            throw new RuntimeException(\sprintf('Langfuse rejected the score "%s" with status %d: "%s"', $feedback->getName(), $status, $response->getContent(false)));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Feedback $feedback): array
    {
        $traceId = $feedback->getTraceId();
        if (null === $traceId || 1 !== preg_match('/^[0-9a-f]{32}$/', $traceId)) {
            throw new InvalidArgumentException(\sprintf('The feedback "%s" of run "%s" has no OpenTelemetry trace ID, Langfuse can only attach scores to traces.', $feedback->getName(), $feedback->getRunId()));
        }

        $value = $feedback->getValue();

        [$value, $dataType] = match (true) {
            \is_bool($value) => [$value ? 1 : 0, 'BOOLEAN'],
            \is_int($value), \is_float($value) => [$value, 'NUMERIC'],
            \is_string($value) => [$value, 'CATEGORICAL'],
            // the signal occurred, e.g. the answer was regenerated
            default => [1, 'BOOLEAN'],
        };

        $createdAt = $feedback->getCreatedAt()->format('Y-m-d\TH:i:s.vP');

        $payload = [
            // Langfuse updates a score sent again with the same ID and timestamp instead of duplicating it
            'id' => $feedback->getId() ?? hash('xxh128', implode("\0", [$feedback->getRunId(), $feedback->getName(), $createdAt])),
            'traceId' => $traceId,
            'name' => $feedback->getName(),
            'value' => $value,
            'dataType' => $dataType,
            'source' => 'API',
            'timestamp' => $createdAt,
            'metadata' => [...$feedback->getMetadata(), 'run_id' => $feedback->getRunId(), 'signal' => $feedback->getSignal()->value, 'source' => $feedback->getSource()->value],
        ];

        if (null !== $feedback->getComment()) {
            $payload['comment'] = $feedback->getComment();
        }

        if (null !== $feedback->getMessageId()) {
            $payload['metadata']['message_id'] = $feedback->getMessageId();
        }

        return $payload;
    }
}
