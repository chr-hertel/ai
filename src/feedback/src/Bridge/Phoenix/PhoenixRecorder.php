<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Feedback\Bridge\Phoenix;

use Symfony\AI\Feedback\Exception\InvalidArgumentException;
use Symfony\AI\Feedback\Exception\RuntimeException;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\FeedbackRecorderInterface;
use Symfony\AI\Feedback\Source;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Records feedback as an annotation on the Arize Phoenix trace of the run.
 *
 * Phoenix attaches trace annotations to traces, so only feedback carrying the trace ID of its run can be recorded.
 * That is the case when the agent is traced with the Telemetry component and Phoenix is the tracing backend.
 *
 * @see https://arize.com/docs/phoenix/sdk-api-reference/rest-api/api-reference/traces/create-trace-annotations
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PhoenixRecorder implements FeedbackRecorderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpoint = 'http://localhost:6006',
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
    ) {
    }

    public function record(Feedback $feedback): void
    {
        $options = ['json' => ['data' => [$this->annotation($feedback)]]];
        if (null !== $this->apiKey) {
            $options['auth_bearer'] = $this->apiKey;
        }

        $response = $this->httpClient->request('POST', rtrim($this->endpoint, '/').'/v1/trace_annotations', $options);

        $status = $response->getStatusCode();
        if ($status >= 300) {
            throw new RuntimeException(\sprintf('Phoenix rejected the annotation "%s" with status %d: "%s"', $feedback->getName(), $status, $response->getContent(false)));
        }
    }

    /**
     * @return array{
     *     trace_id: string,
     *     name: string,
     *     annotator_kind: 'HUMAN'|'CODE',
     *     identifier: string,
     *     result: array{label?: string, score?: int|float, explanation?: string},
     *     metadata: array<string, scalar|null>,
     * }
     */
    private function annotation(Feedback $feedback): array
    {
        $traceId = $feedback->getTraceId();
        if (null === $traceId || 1 !== preg_match('/^[0-9a-f]{32}$/', $traceId)) {
            throw new InvalidArgumentException(\sprintf('The feedback "%s" of run "%s" has no OpenTelemetry trace ID, Phoenix can only annotate traces.', $feedback->getName(), $feedback->getRunId()));
        }

        $value = $feedback->getValue();

        $result = match (true) {
            \is_bool($value) => ['score' => $value ? 1 : 0],
            \is_int($value), \is_float($value) => ['score' => $value],
            \is_string($value) => ['label' => $value],
            // the signal occurred, e.g. the answer was regenerated
            default => ['score' => 1],
        };

        if (null !== $feedback->getComment()) {
            $result['explanation'] = $feedback->getComment();
        }

        $metadata = [...$feedback->getMetadata(), 'run_id' => $feedback->getRunId(), 'signal' => $feedback->getSignal()->value, 'source' => $feedback->getSource()->value];
        if (null !== $feedback->getMessageId()) {
            $metadata['message_id'] = $feedback->getMessageId();
        }

        return [
            'trace_id' => $traceId,
            'name' => $feedback->getName(),
            'annotator_kind' => \in_array($feedback->getSource(), [Source::User, Source::Annotator], true) ? 'HUMAN' : 'CODE',
            // Phoenix updates the annotation with the same name and identifier on the trace instead of adding another one
            'identifier' => $feedback->getId() ?? hash('xxh128', implode("\0", [$feedback->getRunId(), $feedback->getName(), $feedback->getCreatedAt()->format('Y-m-d\TH:i:s.uP')])),
            'result' => $result,
            'metadata' => $metadata,
        ];
    }
}
