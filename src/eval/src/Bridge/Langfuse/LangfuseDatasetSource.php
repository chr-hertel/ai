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
 * The case input is taken from the trace input, the case keeps the trace ID as source run, and the expectations are
 * left to the reviewer.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LangfuseDatasetSource
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $host,
        #[\SensitiveParameter] private readonly string $publicKey,
        #[\SensitiveParameter] private readonly string $secretKey,
    ) {
    }

    /**
     * @param list<string> $labels
     */
    public function pull(string $datasetName, string $scoreName, float|int|string $value, ?\DateTimeInterface $since = null, int $limit = 50, array $labels = []): Dataset
    {
        $query = ['name' => $scoreName, 'value' => $value, 'limit' => $limit];
        if (null !== $since) {
            $query['fromTimestamp'] = $since->format(\DateTimeInterface::RFC3339);
        }

        $cases = [];
        foreach ($this->get('/api/public/scores', $query)['data'] ?? [] as $score) {
            $traceId = $score['traceId'] ?? null;
            if (!\is_string($traceId) || isset($cases[$traceId])) {
                continue;
            }

            $input = $this->input($this->get('/api/public/traces/'.$traceId)['input'] ?? null);
            if (null === $input) {
                continue;
            }

            $cases[$traceId] = new EvalCase($datasetName.'-'.substr($traceId, 0, 8), $input, $labels, sourceRun: $traceId);
        }

        return new Dataset($datasetName, array_values($cases), \sprintf('Traces scored %s = %s, pulled from Langfuse.', $scoreName, $value));
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
