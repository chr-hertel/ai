<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Commentary;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\CommentaryResult;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryComplete;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Simulates the commentary phase of the OpenAI Responses API for platforms that lack it.
 *
 * Every tool passed with the "tools" option gets an additional argument the model fills with a
 * user-facing note on why it calls the tool. That argument is taken out of the tool calls of the
 * result again and reported as commentary instead, the way a platform with a native commentary
 * phase does: as {@see CommentaryResult} next to the tool calls, or streamed as
 * {@see CommentaryStart}, {@see CommentaryDelta} and {@see CommentaryComplete} deltas right before
 * the {@see ToolCallComplete} delta.
 *
 * A tool that already has a parameter of that name is offered unchanged, without commentary.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CommentaryPlatform implements PlatformInterface
{
    public const DEFAULT_ARGUMENT = 'commentary';

    public const DEFAULT_DESCRIPTION = 'A short note for the user on why you are calling this tool and what you expect from it, written in the language of the conversation. It is shown to the user while the tool runs.';

    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $argument = self::DEFAULT_ARGUMENT,
        private readonly string $description = self::DEFAULT_DESCRIPTION,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        $decoratedTools = [];
        if (isset($options['tools']) && \is_array($options['tools'])) {
            $options['tools'] = array_map(function (mixed $tool) use (&$decoratedTools): mixed {
                if (!$tool instanceof Tool) {
                    return $tool;
                }

                $decorated = $this->withCommentary($tool);
                if ($decorated !== $tool) {
                    $decoratedTools[$tool->getName()] = true;
                }

                return $decorated;
            }, $options['tools']);
        }

        $deferredResult = $this->platform->invoke($model, $input, $options);

        if ([] !== $decoratedTools) {
            $deferredResult->onConvert(fn (ResultInterface $result): ResultInterface => $this->convert($result, $decoratedTools));
        }

        return $deferredResult;
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }

    private function withCommentary(Tool $tool): Tool
    {
        $parameters = $tool->getParameters() ?? ['type' => 'object', 'properties' => [], 'required' => [], 'additionalProperties' => false];

        if (isset($parameters['properties'][$this->argument])) {
            $this->logger->warning('Tool "{tool}" already has a parameter named "{argument}", it is offered without commentary.', [
                'tool' => $tool->getName(),
                'argument' => $this->argument,
            ]);

            return $tool;
        }

        $parameters['properties'][$this->argument] = ['type' => 'string', 'description' => $this->description];
        $parameters['required'] = [...$parameters['required'] ?? [], $this->argument];

        return new Tool($tool->getReference(), $tool->getName(), $tool->getDescription(), $parameters, $tool->getMetadata());
    }

    /**
     * @param array<string, true> $decoratedTools
     */
    private function convert(ResultInterface $result, array $decoratedTools): ResultInterface
    {
        if ($result instanceof StreamResult) {
            return $this->convertStream($result, $decoratedTools);
        }

        if ($result instanceof ToolCallResult) {
            [$commentaries, $toolCallResult] = $this->extract($result, $decoratedTools);
            $parts = [...$commentaries, $toolCallResult];

            return $this->replace($result, 1 === \count($parts) ? $toolCallResult : new MultiPartResult($parts));
        }

        if ($result instanceof MultiPartResult && null !== $result->asToolCallResult()) {
            $parts = [];
            foreach ($result->getContent() as $part) {
                if ($part instanceof ToolCallResult) {
                    [$commentaries, $part] = $this->extract($part, $decoratedTools);
                    $parts = [...$parts, ...$commentaries];
                }

                $parts[] = $part;
            }

            return $this->replace($result, new MultiPartResult($parts));
        }

        return $result;
    }

    /**
     * @param array<string, true> $decoratedTools
     */
    private function convertStream(StreamResult $stream, array $decoratedTools): StreamResult
    {
        return $converted = new StreamResult((function () use (&$converted, $stream, $decoratedTools): \Generator {
            foreach ($stream->getContent() as $delta) {
                if (!$delta instanceof ToolCallComplete || [] === $delta->getToolCalls()) {
                    yield $delta;

                    continue;
                }

                [$commentaries, $toolCallResult] = $this->extract(new ToolCallResult($delta->getToolCalls()), $decoratedTools);
                foreach ($commentaries as $commentary) {
                    yield new CommentaryStart();
                    yield new CommentaryDelta($commentary->getContent());
                    yield new CommentaryComplete($commentary->getContent());
                }

                yield new ToolCallComplete($toolCallResult->getContent());
            }

            // the metadata got promoted to the inner stream while it was consumed
            $converted->getMetadata()->merge($stream->getMetadata());
        })());
    }

    /**
     * Takes the commentary argument out of the tool calls of decorated tools.
     *
     * @param array<string, true> $decoratedTools
     *
     * @return array{list<CommentaryResult>, ToolCallResult}
     */
    private function extract(ToolCallResult $result, array $decoratedTools): array
    {
        $commentaries = [];
        $toolCalls = [];
        foreach ($result->getContent() as $toolCall) {
            $arguments = $toolCall->getArguments();
            if (!isset($decoratedTools[$toolCall->getName()]) || !\array_key_exists($this->argument, $arguments)) {
                $toolCalls[] = $toolCall;

                continue;
            }

            $commentary = $arguments[$this->argument];
            if (\is_string($commentary) && '' !== trim($commentary)) {
                $commentaries[] = new CommentaryResult($commentary);
            }

            unset($arguments[$this->argument]);
            $toolCalls[] = new ToolCall($toolCall->getId(), $toolCall->getName(), $arguments, $toolCall->getSignature());
        }

        $stripped = new ToolCallResult($toolCalls);
        $stripped->getMetadata()->merge($result->getMetadata());

        return [$commentaries, $stripped];
    }

    private function replace(ResultInterface $result, ResultInterface $replacement): ResultInterface
    {
        $replacement->getMetadata()->merge($result->getMetadata());

        if (null !== $rawResult = $result->getRawResult()) {
            $replacement->setRawResult($rawResult);
        }

        return $replacement;
    }
}
