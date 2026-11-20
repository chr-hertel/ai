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
use Symfony\AI\Eval\Exception\InvalidArgumentException;
use Symfony\AI\Eval\Exception\RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads and writes Langfuse datasets, and turns production traces with a given score, e.g. a thumbs down, into eval
 * cases to review and label.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LangfuseDatasetSource
{
    public const METADATA_ITEM_ID = 'langfuse_item_id';
    public const METADATA_DATASET_ID = 'langfuse_dataset_id';

    private const PAGE_SIZE = 100;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $host,
        #[\SensitiveParameter] private readonly string $publicKey,
        #[\SensitiveParameter] private readonly string $secretKey,
    ) {
    }

    /**
     * Fetches the active items of a Langfuse dataset as eval cases.
     *
     * An expected output object becomes the expectations of the case, any other expected output is kept as the
     * "expected_output" expectation. The "labels" and "fixtures" of the item metadata become those of the case.
     */
    public function fetch(string $datasetName): Dataset
    {
        $dataset = $this->get('/api/public/v2/datasets/'.rawurlencode($datasetName));
        $datasetId = $dataset['id'] ?? null;
        $description = $dataset['description'] ?? null;

        $cases = [];
        $query = ['datasetName' => $datasetName, 'page' => 1, 'limit' => self::PAGE_SIZE];
        do {
            $response = $this->get('/api/public/dataset-items', $query);
            $items = $response['data'] ?? [];

            foreach (\is_array($items) ? $items : [] as $item) {
                $itemId = \is_array($item) ? ($item['id'] ?? null) : null;
                if (!\is_array($item) || !\is_string($itemId) || 'ACTIVE' !== ($item['status'] ?? 'ACTIVE')) {
                    continue;
                }

                if (null !== $case = $this->case($itemId, $item, \is_string($datasetId) ? $datasetId : null)) {
                    $cases[] = $case;
                }
            }

            $meta = $response['meta'] ?? null;
            $totalPages = \is_array($meta) && \is_int($meta['totalPages'] ?? null) ? $meta['totalPages'] : 0;
        } while ($query['page']++ < $totalPages);

        return new Dataset($datasetName, $cases, \is_string($description) && '' !== $description ? $description : null);
    }

    /**
     * Adds an item to a Langfuse dataset, with the input of the source trace's root observation if no input is given.
     *
     * @param list<array<string, mixed>>|string|null $input    a user message, or a conversation of {user: ...} or {role, content} messages
     * @param array<string, mixed>                   $metadata
     *
     * @return string the ID of the item
     */
    public function addItem(string $datasetName, array|string|null $input = null, mixed $expectedOutput = null, array $metadata = [], ?string $sourceTraceId = null, ?string $id = null): string
    {
        $input = $this->input(null === $input && null !== $sourceTraceId ? $this->rootInput($sourceTraceId) : $input);
        if (null === $input || '' === $input) {
            throw new InvalidArgumentException(null === $sourceTraceId ? 'A dataset item requires a user message or a conversation as input.' : \sprintf('The trace "%s" has no input to take for the dataset item.', $sourceTraceId));
        }

        $payload = array_filter([
            'datasetName' => $datasetName,
            'id' => $id,
            'input' => \is_string($input) ? $input : $this->messages($input),
            'expectedOutput' => $expectedOutput,
            'metadata' => [] === $metadata ? null : $metadata,
            'sourceTraceId' => $sourceTraceId,
        ], static fn (mixed $value): bool => null !== $value);

        $response = $this->httpClient->request('POST', rtrim($this->host, '/').'/api/public/dataset-items', [
            'auth_basic' => [$this->publicKey, $this->secretKey],
            'json' => $payload,
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('Langfuse rejected the item of dataset "%s" with status %d: "%s"', $datasetName, $response->getStatusCode(), $response->getContent(false)));
        }

        $itemId = $response->toArray()['id'] ?? null;
        if (!\is_string($itemId)) {
            throw new RuntimeException(\sprintf('Langfuse answered without the ID of the item of dataset "%s".', $datasetName));
        }

        return $itemId;
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

    /**
     * @param array<mixed> $item
     */
    private function case(string $id, array $item, ?string $datasetId): ?EvalCase
    {
        $rawInput = $item['input'] ?? null;
        if (null === $rawInput) {
            return null;
        }

        // structured input the agent cannot take as messages is given to it as JSON
        $input = $this->input($rawInput) ?? (\is_string($rawInput) ? $rawInput : json_encode($rawInput, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

        $expectedOutput = $item['expectedOutput'] ?? null;
        $expectations = match (true) {
            null === $expectedOutput => [],
            \is_array($expectedOutput) && !array_is_list($expectedOutput) => $expectedOutput,
            default => ['expected_output' => $expectedOutput],
        };

        $metadata = \is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
        $labels = \is_array($metadata['labels'] ?? null) ? array_values(array_map(strval(...), $metadata['labels'])) : [];
        $fixtures = \is_array($metadata['fixtures'] ?? null) ? $metadata['fixtures'] : [];
        unset($metadata['labels'], $metadata['fixtures']);

        $sourceTraceId = $item['sourceTraceId'] ?? null;

        return new EvalCase(
            $id,
            $input,
            $labels,
            $fixtures,
            $expectations,
            \is_string($sourceTraceId) ? $sourceTraceId : null,
            array_filter([...$metadata, self::METADATA_ITEM_ID => $id, self::METADATA_DATASET_ID => $datasetId], static fn (mixed $value): bool => null !== $value),
        );
    }

    /**
     * @param list<array{user?: string, assistant?: string}> $input
     *
     * @return list<array{role: string, content: string}>
     */
    private function messages(array $input): array
    {
        $messages = [];
        foreach ($input as $message) {
            foreach ($message as $role => $content) {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        return $messages;
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

        // GenAI semantic convention messages: [{role, parts: [{type: text, content}]}], plain {role, content}, or the dataset format
        $messages = [];
        foreach ($input as $message) {
            if (\is_array($message) && !isset($message['role']) && 1 === \count($message) && \is_string($message['user'] ?? $message['assistant'] ?? null)) {
                $messages[] = $message;
                continue;
            }

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
