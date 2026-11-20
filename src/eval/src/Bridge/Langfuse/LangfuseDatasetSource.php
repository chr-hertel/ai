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

use Symfony\AI\Eval\Dataset;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\Exception\RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Turns production traces with a given score, e.g. a thumbs down, into eval cases to review and label.
 *
 * The case input is taken from the input of the trace's root observation, the case keeps the trace ID as source run,
 * and the expectations are left to the reviewer.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LangfuseDatasetSource
{
    private const PAGE_SIZE = 100;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $host,
        #[\SensitiveParameter] private readonly string $publicKey,
        #[\SensitiveParameter] private readonly string $secretKey,
    ) {
    }

    /**
     * @param bool|float|int|string $value  matched against boolean, numeric or categorical scores depending on its type
     * @param int                   $limit  the maximum number of traces, i.e. cases
     * @param list<string>          $labels
     */
    public function pull(string $datasetName, string $scoreName, bool|float|int|string $value, ?\DateTimeInterface $since = null, int $limit = 50, array $labels = []): Dataset
    {
        [$queryValue, $dataType] = match (true) {
            \is_bool($value) => [$value ? 'true' : 'false', 'BOOLEAN'],
            \is_string($value) => [$value, 'CATEGORICAL'],
            default => [(string) $value, 'NUMERIC'],
        };

        $query = ['name' => $scoreName, 'dataType' => $dataType, 'value' => $queryValue, 'fields' => 'subject', 'limit' => min($limit, self::PAGE_SIZE)];
        if (null !== $since) {
            $query['fromTimestamp'] = $since->format(\DateTimeInterface::RFC3339);
        }

        $cases = [];
        $seen = [];
        while (true) {
            $response = $this->get('/api/public/v3/scores', $query);
            $scores = $response['data'] ?? [];

            foreach (\is_array($scores) ? $scores : [] as $score) {
                $traceId = $this->traceId($score);
                if (null === $traceId || isset($seen[$traceId])) {
                    continue;
                }

                $seen[$traceId] = true;
                $input = $this->input($this->rootInput($traceId));
                if (null === $input) {
                    continue;
                }

                $cases[] = new EvalCase($datasetName.'-'.$traceId, $input, $labels, sourceRun: $traceId);
                if (\count($cases) >= $limit) {
                    break 2;
                }
            }

            $meta = $response['meta'] ?? null;
            $cursor = \is_array($meta) ? ($meta['cursor'] ?? null) : null;
            if (!\is_string($cursor) || '' === $cursor) {
                break;
            }

            $query['cursor'] = $cursor;
        }

        return new Dataset($datasetName, $cases, \sprintf('Traces scored %s = %s, pulled from Langfuse.', $scoreName, $queryValue));
    }

    private function traceId(mixed $score): ?string
    {
        $subject = \is_array($score) ? ($score['subject'] ?? null) : null;
        if (!\is_array($subject)) {
            return null;
        }

        $traceId = match ($subject['kind'] ?? null) {
            'trace' => $subject['id'] ?? null,
            'observation' => $subject['traceId'] ?? null,
            default => null,
        };

        return \is_string($traceId) && '' !== $traceId ? $traceId : null;
    }

    private function rootInput(string $traceId): mixed
    {
        $observations = $this->get('/api/public/v2/observations', ['traceId' => $traceId, 'isRootObservation' => 'true', 'fields' => 'core,io', 'limit' => 1])['data'] ?? null;
        $root = \is_array($observations) ? ($observations[0] ?? null) : null;

        return \is_array($root) ? ($root['input'] ?? null) : null;
    }

    /**
     * @return list<array{user?: string, assistant?: string}>|string|null
     */
    private function input(mixed $input): array|string|null
    {
        if (\is_string($input)) {
            $decoded = json_decode($input, true);

            return \is_array($decoded) ? $this->input($decoded) : $input;
        }

        if (!\is_array($input)) {
            return null;
        }

        // GenAI semantic convention messages: [{role, parts: [{type: text, content}]}], or plain {role, content}
        $messages = [];
        foreach ($input as $message) {
            if (!\is_array($message) || !\in_array($message['role'] ?? null, ['user', 'assistant'], true)) {
                continue;
            }

            $text = $message['content'] ?? null;
            if (!\is_string($text) && isset($message['parts']) && \is_array($message['parts'])) {
                $text = implode("\n", array_map(static fn (array $part): string => (string) ($part['content'] ?? ''), array_filter($message['parts'], static fn (mixed $part): bool => \is_array($part) && 'text' === ($part['type'] ?? null))));
            }

            if (\is_string($text) && '' !== $text) {
                $messages[] = [$message['role'] => $text];
            }
        }

        return [] === $messages ? null : $messages;
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $response = $this->httpClient->request('GET', rtrim($this->host, '/').$path, [
            'auth_basic' => [$this->publicKey, $this->secretKey],
            'query' => $query,
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('Langfuse answered "%s" with status %d.', $path, $response->getStatusCode()));
        }

        return $response->toArray();
    }
}
