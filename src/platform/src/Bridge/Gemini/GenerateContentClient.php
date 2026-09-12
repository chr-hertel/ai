<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

use Symfony\AI\Platform\Bridge\Gemini\Gemini\FinishReasonMapper;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\TokenUsageExtractor;
use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\FinishReason\FinishReasonAwareTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ChoiceDelta;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\TransportInterface;

/**
 * Google `generateContent` / `streamGenerateContent` contract handler.
 *
 * Shared between the Google AI Studio (`generativelanguage.googleapis.com`)
 * and Vertex AI (`aiplatform.googleapis.com`) transports — the only
 * meaningful contract-level difference is the JSON-schema response key
 * (Gemini direct expects `responseJsonSchema`, Vertex AI expects
 * `responseSchema`), exposed via a constructor flag.
 *
 * @phpstan-type Part array{
 *     functionCall?: array{id?: string, name: string, args: mixed[]},
 *     text?: string,
 *     thought?: bool,
 *     thoughtSignature?: string,
 *     inlineData?: array{data: string, mimeType: string},
 *     executableCode?: array{language: string, code: string},
 *     codeExecutionResult?: array{id?: string, outcome: self::OUTCOME_*, output: string},
 * }
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class GenerateContentClient implements EndpointClientInterface
{
    use FinishReasonAwareTrait;

    public const ENDPOINT = 'google.generate_content';

    public const RESPONSE_SCHEMA_KEY_GEMINI = 'responseJsonSchema';
    public const RESPONSE_SCHEMA_KEY_VERTEX_AI = 'responseSchema';

    public const OUTCOME_OK = 'OUTCOME_OK';
    public const OUTCOME_FAILED = 'OUTCOME_FAILED';

    /**
     * @param class-string<Model> $modelClass The model family this provider serves through
     *                                        this contract
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $responseSchemaKey = self::RESPONSE_SCHEMA_KEY_GEMINI,
        private readonly string $modelClass = Gemini::class,
    ) {
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    /**
     * Shared by the Google AI Studio and Vertex AI providers, which register
     * it with their own model class.
     */
    public function supports(Model $model): bool
    {
        return $model instanceof $this->modelClass;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $isStream = $options['stream'] ?? false;
        $method = $isStream ? 'streamGenerateContent' : 'generateContent';

        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $options['responseMimeType'] = 'application/json';
            $options[$this->responseSchemaKey] = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'];
            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        $config = ['generationConfig' => $options];
        unset(
            $config['generationConfig']['stream'],
            $config['generationConfig']['tools'],
            $config['generationConfig']['tool_config'],
            $config['generationConfig']['server_tools'],
        );

        if ([] === $config['generationConfig']) {
            $config = [];
        }

        if (isset($options['tools'])) {
            $config['tools'][] = ['functionDeclarations' => $options['tools']];
        }

        if (isset($options['tool_config'])) {
            $config['tool_config'] = $options['tool_config'];
        }

        foreach ($options['server_tools'] ?? [] as $tool => $params) {
            if (!$params) {
                continue;
            }
            $config['tools'][] = [$tool => true === $params ? new \ArrayObject() : $params];
        }

        // Ask for SSE framing so the stream arrives as discrete events rather than partial JSON.
        $path = \sprintf('models/%s:%s', $model->getName(), $method);
        if ($isStream) {
            $path .= '?alt=sse';
        }

        $envelope = new RequestEnvelope(
            payload: array_merge($config, $payload),
            path: $path,
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($raw));
        }

        $data = $raw->getData();

        if (isset($data['error'])) {
            $code = $data['error']['code'] ?? '-';
            $status = $data['error']['status'] ?? '-';
            $message = $data['error']['message'] ?? 'Unknown error';
            throw new RuntimeException(\sprintf('Error "%s" - "%s": "%s".', $code, $status, $message));
        }

        if (!isset($data['candidates'][0]['content']['parts'][0])) {
            // Gemini can return a well-formed completion with a terminal finish reason but no
            // content parts (e.g. an empty message after a tool result). Treat it as empty text
            // instead of crashing on an otherwise valid response.
            if (isset($data['candidates'][0]['finishReason'])) {
                return $this->withFinishReason(
                    new TextResult(''),
                    FinishReasonMapper::map($data['candidates'][0]['finishReason']),
                );
            }

            throw new RuntimeException('Response does not contain any content.');
        }

        $choices = array_map($this->convertChoice(...), $data['candidates']);

        return $this->withFinishReason(
            1 === \count($choices) ? $choices[0] : new ChoiceResult($choices),
            FinishReasonMapper::map($data['candidates'][0]['finishReason'] ?? null),
        );
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $finishReason = null;
        // Thinking boundary state, carried across chunks: Gemini streams thought parts (often split
        // over several chunks) before the answer, so a thinking block may span multiple iterations.
        $thinking = null;
        $thinkingSignature = null;
        // Tool calls are collected across the whole stream and completed once at its end, so that
        // calls split over several parts or chunks arrive as a single batch.
        $toolCalls = [];

        foreach ($result->getDataStream() as $data) {
            if (isset($data['usageMetadata']['totalTokenCount']) && 0 < $data['usageMetadata']['totalTokenCount']) {
                yield $this->getTokenUsageExtractor()->fromUsageMetadata($data['usageMetadata'], $data['modelVersion'] ?? null);
            }

            // Gemini repeats the reason on every candidate of the terminal chunk; the leading one wins,
            // matching the buffered path.
            if (null !== ($data['candidates'][0]['finishReason'] ?? null)) {
                $finishReason ??= FinishReasonMapper::map($data['candidates'][0]['finishReason']);
            }

            $choices = array_values(array_filter(array_map($this->convertChoice(...), $data['candidates'] ?? [])));

            if ([] === $choices) {
                continue;
            }

            // The multi-candidate path is exotic for Gemini; preserve its bare-delta behavior.
            if (1 !== \count($choices)) {
                $deltas = [];
                foreach ($choices as $choice) {
                    $deltas = array_merge($deltas, iterator_to_array($this->resultToDeltas($choice), false));
                }

                if ([] !== $deltas) {
                    yield new ChoiceDelta($deltas);
                }

                continue;
            }

            // A single candidate may carry multiple parts (e.g. a thought part plus text, or a tool
            // call plus text) that convertChoice() returns as a MultiPartResult; flatten to leaves so
            // thought and non-thought parts are framed identically whether combined in one chunk or
            // split across chunks.
            foreach ($this->flattenResult($choices[0]) as $leaf) {
                if ($leaf instanceof ThinkingResult) {
                    if (null === $thinking) {
                        yield new ThinkingStart();
                        $thinking = '';
                    }

                    $content = $leaf->getContent() ?? '';
                    $thinking .= $content;

                    if (null !== $leaf->getSignature()) {
                        $thinkingSignature = $leaf->getSignature();
                    }

                    yield new ThinkingDelta($content);

                    continue;
                }

                // The first non-thinking part closes an open thinking block.
                if (null !== $thinking) {
                    yield new ThinkingComplete($thinking, $thinkingSignature);
                    $thinking = null;
                    $thinkingSignature = null;
                }

                // Gemini delivers each function call as a complete part, so a call is announced at the
                // position its part appears in and batched into the terminal ToolCallComplete.
                if ($leaf instanceof ToolCallResult) {
                    foreach ($leaf->getContent() as $toolCall) {
                        $toolCalls[] = $toolCall;

                        // Gemini < 3.0 leaves the function call id empty; without one there is nothing
                        // to correlate the announcement with, so the call is only batched.
                        if ('' !== $toolCall->getId()) {
                            yield new ToolCallStart($toolCall->getId(), $toolCall->getName());
                        }
                    }

                    continue;
                }

                yield from $this->resultToDeltas($leaf);
            }
        }

        // A thinking block still open at the end of the stream is completed before the terminal metadata.
        if (null !== $thinking) {
            yield new ThinkingComplete($thinking, $thinkingSignature);
        }

        if ([] !== $toolCalls) {
            yield new ToolCallComplete($toolCalls);
        }

        // Emitted last: the terminal chunk carries both the finish reason and its content parts.
        if (null !== $finishReason) {
            yield new MetadataDelta('finish_reason', $finishReason);
        }
    }

    /**
     * Flattens a single choice into its leaf results so the streaming thinking-boundary logic can walk
     * thought and non-thought parts uniformly, whether they arrive combined in one MultiPartResult
     * chunk or split across chunks.
     *
     * @return list<ResultInterface>
     */
    private function flattenResult(ResultInterface $result): array
    {
        if (!$result instanceof MultiPartResult) {
            return [$result];
        }

        $leaves = [];
        foreach ($result->getContent() as $part) {
            foreach ($this->flattenResult($part) as $leaf) {
                $leaves[] = $leaf;
            }
        }

        return $leaves;
    }

    /**
     * ExecutableCodeResult and CodeExecutionResult have no streaming delta representation and are
     * only exposed through the buffered result; they are skipped here instead of crashing.
     *
     * @return \Generator<DeltaInterface>
     */
    private function resultToDeltas(ResultInterface $result): \Generator
    {
        switch (true) {
            case $result instanceof MultiPartResult:
                foreach ($result->getContent() as $part) {
                    yield from $this->resultToDeltas($part);
                }

                return;
            case $result instanceof ThinkingResult:
                yield new ThinkingDelta($result->getContent() ?? '');

                return;
            case $result instanceof TextResult:
                yield new TextDelta($result->getContent());

                return;
            case $result instanceof BinaryResult:
                yield new BinaryDelta($result->getContent(), $result->getMimeType());

                return;
            case $result instanceof ToolCallResult:
                // Only reached through the multi-candidate path: the single-candidate stream batches
                // tool calls into one terminal ToolCallComplete instead.
                yield new ToolCallComplete($result->getContent());

                return;
        }
    }

    /**
     * @param array{
     *     finishReason?: string,
     *     content?: array{parts: list<array<string, mixed>>},
     * } $choice
     */
    private function convertChoice(array $choice): ToolCallResult|TextResult|ThinkingResult|BinaryResult|ExecutableCodeResult|CodeExecutionResult|MultiPartResult|null
    {
        if (!isset($choice['content']['parts'])) {
            return null;
        }

        $parts = $choice['content']['parts'];

        return match (\count($parts)) {
            1 => $this->convertPart($parts[0]),
            default => new MultiPartResult(array_values(array_filter(array_map($this->convertPart(...), $parts)))),
        };
    }

    /**
     * @param array<string, mixed> $contentPart
     */
    private function convertPart(array $contentPart): ToolCallResult|TextResult|ThinkingResult|BinaryResult|ExecutableCodeResult|CodeExecutionResult|null
    {
        $signature = $contentPart['thoughtSignature'] ?? null;

        return match (true) {
            isset($contentPart['functionCall']) => new ToolCallResult([new ToolCall(
                $contentPart['functionCall']['id'] ?? '',
                $contentPart['functionCall']['name'],
                $this->normalizeArguments($contentPart['functionCall']['args'] ?? []),
                $signature,
            )]),
            true === ($contentPart['thought'] ?? false) => new ThinkingResult($contentPart['text'] ?? '', $signature),
            isset($contentPart['text']) => new TextResult($contentPart['text'], $signature),
            isset($contentPart['inlineData']) => BinaryResult::fromBase64(
                $contentPart['inlineData']['data'],
                $contentPart['inlineData']['mimeType'] ?? null,
            ),
            isset($contentPart['executableCode']) => new ExecutableCodeResult(
                $contentPart['executableCode']['code'],
                $contentPart['executableCode']['language'],
                $contentPart['executableCode']['id'] ?? null,
            ),
            isset($contentPart['codeExecutionResult']) => new CodeExecutionResult(
                self::OUTCOME_OK === $contentPart['codeExecutionResult']['outcome'],
                $contentPart['codeExecutionResult']['output'],
                $contentPart['codeExecutionResult']['id'] ?? null,
            ),
            default => null,
        };
    }

    /**
     * Gemini emits empty strings for optional object properties it has no value for, whereas other
     * providers omit them or send null. Coerce those empty strings to null (recursing into nested
     * structures) so downstream denormalization — e.g. of nullable DateTime properties — behaves
     * consistently across bridges. List elements (integer-keyed) are left untouched, as an empty
     * string can be a legitimate value inside a list argument.
     *
     * @param mixed[] $arguments
     *
     * @return mixed[]
     */
    private function normalizeArguments(array $arguments): array
    {
        foreach ($arguments as $key => $value) {
            if (\is_array($value)) {
                $arguments[$key] = $this->normalizeArguments($value);
            } elseif ('' === $value && !\is_int($key)) {
                $arguments[$key] = null;
            }
        }

        return $arguments;
    }
}
