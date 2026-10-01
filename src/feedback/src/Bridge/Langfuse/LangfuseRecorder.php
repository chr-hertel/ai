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

use Symfony\AI\Feedback\Exception\RuntimeException;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\FeedbackRecorderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Records feedback as a score on the Langfuse trace of the run.
 *
 * This requires the run ID to be the trace ID, which is the case when the agent is traced with the OpenTelemetry
 * bridge and Langfuse is the tracing backend.
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

        if ($response->getStatusCode() >= 300) {
            throw new RuntimeException(\sprintf('Langfuse rejected the score "%s" with status %d: "%s"', $feedback->getName(), $response->getStatusCode(), $response->getContent(false)));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Feedback $feedback): array
    {
        $value = $feedback->getValue();

        [$value, $dataType] = match (true) {
            \is_bool($value) => [$value ? 1 : 0, 'BOOLEAN'],
            \is_int($value), \is_float($value) => [$value, 'NUMERIC'],
            \is_string($value) => [$value, 'CATEGORICAL'],
            default => [1, 'NUMERIC'],
        };

        $payload = [
            // idempotent per run, signal and creation time, retries do not duplicate scores
            'id' => hash('xxh128', $feedback->getRunId().$feedback->getName().$feedback->getCreatedAt()->format('U.u')),
            'traceId' => $feedback->getRunId(),
            'name' => $feedback->getName(),
            'value' => $value,
            'dataType' => $dataType,
            'source' => 'API',
            'metadata' => [...$feedback->getMetadata(), 'signal' => $feedback->getSignal()->value, 'source' => $feedback->getSource()->value],
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
