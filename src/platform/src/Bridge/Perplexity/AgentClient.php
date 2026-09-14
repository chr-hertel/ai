<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity;

use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesClient;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\HttpStreamInterface;
use Symfony\AI\Platform\Result\Stream\SseStream;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to the Perplexity Agent API, which follows the Responses API schema.
 *
 * @phpstan-type SearchResult array{id: int, url: string, title: string, snippet: string, date?: string, last_updated?: string, source?: string}
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AgentClient extends ResponsesClient
{
    use HttpStatusErrorHandlingTrait;

    /**
     * Sonar Chat Completions models and the preset Perplexity recommends in their place.
     */
    private const SONAR_MODEL_PRESETS = [
        'sonar' => 'fast',
        'sonar-pro' => 'fast',
        'sonar-reasoning' => 'low',
        'sonar-reasoning-pro' => 'low',
        'sonar-deep-research' => 'high',
    ];

    /**
     * Output items the Agent API reports for its built-in tools, next to the Responses API ones.
     */
    private const BUILT_IN_TOOL_OUTPUT_TYPES = [
        'search_results',
        'fetch_url_results',
        'finance_results',
        'people_search_results',
        'sandbox_results',
        'tool_search_output',
    ];

    /**
     * @param string $baseUrl Base URL of a Perplexity-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] string $apiKey,
        string $baseUrl = 'https://api.perplexity.ai',
    ) {
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }

        if (!str_starts_with($apiKey, 'pplx-')) {
            throw new InvalidArgumentException('The API key must start with "pplx-".');
        }

        parent::__construct($httpClient, $baseUrl, $apiKey, '/v1/agent');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Perplexity;
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        if ($raw instanceof RawHttpResult) {
            $response = $raw->getObject();

            if (400 === $response->getStatusCode()) {
                $error = json_decode($response->getContent(false), true)['error'] ?? [];
                $message = $error['message'] ?? '';

                if ('too_many_prompt_tokens' === ($error['type'] ?? null) || str_contains(strtolower($message), 'too long')) {
                    throw new ExceedContextSizeException('' !== $message ? $message : 'Context size exceeded');
                }
            }

            $this->throwOnHttpError($response);
        }

        if ($options['stream'] ?? false) {
            $searchResultItems = [];
            $events = $this->filterStreamEvents($raw->getDataStream(), $this->getFunctionNames($options), $searchResultItems);

            /** @var StreamResult $stream */
            $stream = parent::convert(new InMemoryRawResult(dataStream: $events, object: $raw->getObject()), $options);

            return new StreamResult($this->appendSearchResults($stream->getContent(), $searchResultItems));
        }

        $data = $raw->getData();
        $searchResults = [];

        if (\is_array($data['output'] ?? null)) {
            $searchResults = self::collectSearchResults($data['output']);
            $data['output'] = array_values(array_filter(
                $data['output'],
                static fn (array $item): bool => !\in_array($item['type'] ?? null, self::BUILT_IN_TOOL_OUTPUT_TYPES, true),
            ));
        }

        $result = parent::convert(new InMemoryRawResult($data, object: $raw->getObject()), $options);

        if ([] !== $searchResults) {
            $result->getMetadata()->add('search_results', $searchResults);
            $result->getMetadata()->add('citations', array_column($searchResults, 'url'));
        }

        return $result;
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * Unlike the Responses API, the Agent API takes structured output as a top-level "response_format".
     */
    protected function createBody(Model $model, array $payload, array $options): array
    {
        if (isset($options['max_tokens'])) {
            $options['max_output_tokens'] ??= $options['max_tokens'];
            unset($options['max_tokens']);
        }

        $name = $model->getName();

        if (isset(self::SONAR_MODEL_PRESETS[$name])) {
            @trigger_error(\sprintf('Since symfony/ai-perplexity-platform 0.15: The "%s" model is deprecated, use the "%s" preset instead.', $name, self::SONAR_MODEL_PRESETS[$name]), \E_USER_DEPRECATED);

            $name = self::SONAR_MODEL_PRESETS[$name];
        }

        // Agent API model identifiers are provider-prefixed, e.g. "perplexity/sonar"; anything else names a preset
        $target = str_contains($name, '/') ? ['model' => $name] : ['preset' => $name];

        return array_merge($options, $target, $payload);
    }

    protected function createStreamParser(): HttpStreamInterface
    {
        return new SseStream();
    }

    /**
     * Drops the built-in tool calls (e.g. "search_web"), announced as function calls that never complete.
     *
     * @param iterable<array<string, mixed>> $events
     * @param list<string>                   $functionNames
     * @param list<mixed>                    $searchResultItems
     *
     * @return \Generator<array<string, mixed>>
     */
    private function filterStreamEvents(iterable $events, array $functionNames, array &$searchResultItems): \Generator
    {
        foreach ($events as $event) {
            $item = $event['item'] ?? null;

            if ('response.output_item.done' === ($event['type'] ?? null) && 'search_results' === ($item['type'] ?? null)) {
                $searchResultItems[] = $item;
            }

            if ('function_call' === ($item['type'] ?? null) && !\in_array($item['name'] ?? null, $functionNames, true)) {
                continue;
            }

            yield $event;
        }
    }

    /**
     * @param list<mixed> $searchResultItems
     */
    private function appendSearchResults(\Generator $deltas, array &$searchResultItems): \Generator
    {
        yield from $deltas;

        $searchResults = self::collectSearchResults($searchResultItems);

        if ([] !== $searchResults) {
            yield new MetadataDelta('search_results', $searchResults);
            yield new MetadataDelta('citations', array_column($searchResults, 'url'));
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<string>
     */
    private function getFunctionNames(array $options): array
    {
        $names = [];

        foreach ($options['tools'] ?? [] as $tool) {
            if ('function' === ($tool['type'] ?? null) && isset($tool['name'])) {
                $names[] = $tool['name'];
            }
        }

        return $names;
    }

    /**
     * Sorted by id, as the Agent API numbers the results of a run across its searches but not in output order.
     *
     * @param array<mixed> $output
     *
     * @return list<SearchResult>
     */
    private static function collectSearchResults(array $output): array
    {
        $searchResults = [];

        foreach ($output as $item) {
            if ('search_results' === ($item['type'] ?? null)) {
                $searchResults = [...$searchResults, ...($item['results'] ?? [])];
            }
        }

        usort($searchResults, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $searchResults;
    }
}
