<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\AgentResult;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\Context\ResultAwareContextProcessorInterface;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialObjectStreamListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Drives a single agent invocation, producing the generator of updates an {@see Execution} wraps.
 *
 * The tool-calling loop is iterative: every round invokes the model, executes the tool calls it requested
 * and feeds the results back, until the model answers without asking for further tools. Streamed rounds
 * are consumed here as well, so a streamed tool call is just another round of that same loop.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class Runner
{
    /**
     * @param list<ContextProcessorInterface> $contextProcessors
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly array $contextProcessors = [],
        private readonly ?ToolExecutorInterface $toolExecutor = null,
        private readonly ?int $maxToolCalls = 50,
        private readonly bool $excludeToolMessages = false,
        private readonly bool $includeSources = false,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ToolResultConverter $resultConverter = new ToolResultConverter(),
    ) {
    }

    /**
     * @param non-empty-string     $model
     * @param array<string, mixed> $options
     *
     * @return \Generator<int, UpdateInterface, mixed, void>
     */
    public function run(AgentInterface $agent, string $model, MessageBag $messages, Context $context, array $options): \Generator
    {
        $messages = $this->excludeToolMessages ? clone $messages : $messages;

        $request = new AgentRequest($model, $messages, $options, $context);
        $agentContext = new AgentContext($agent);

        foreach ($this->applicableProcessors($context) as $processor) {
            $processor->process($request, $agentContext);
            yield from $agentContext->flushUpdates();
        }

        $model = $request->getModel();
        $messages = $request->getMessageBag();
        $options = $request->getOptions();

        $sources = new SourceCollection();
        $metadata = new Metadata();
        $iterations = 0;

        while (true) {
            yield new Progress('model_request', 'Invoking model.', $model);

            $result = $this->platform->invoke($model, $messages, $options)->getResult();

            $assistantMessage = null;
            if ($result instanceof StreamResult) {
                [$result, $assistantMessage] = yield from $this->consumeStream($result);
            }

            $toolCallResult = $this->extractToolCallResult($result);
            if (null === $toolCallResult || null === $this->toolExecutor) {
                break;
            }

            // $metadata aggregates the tool calling rounds, the final result carries its own
            $metadata->merge($result->getMetadata());

            if (null !== $this->maxToolCalls && ++$iterations > $this->maxToolCalls) {
                throw new MaxIterationsExceededException($this->maxToolCalls);
            }

            $toolCalls = array_values($toolCallResult->getContent());
            $toolResults = yield from $this->toolExecutor->execute($toolCalls);

            $messages->add($assistantMessage ?? Message::ofAssistant($result));
            foreach ($toolResults as $i => $toolResult) {
                $messages->add(Message::ofToolCall($toolCalls[$i], $this->resultConverter->convert($toolResult)));

                if (null !== $toolResult->getSources()) {
                    $sources = $sources->merge($toolResult->getSources());
                }
            }

            $event = new ToolCallsExecuted($toolResults);
            $this->eventDispatcher?->dispatch($event);

            if ($event->hasResult()) {
                $result = $event->getResult();

                break;
            }
        }

        $result->getMetadata()->merge($metadata);

        if ($this->includeSources) {
            $result->getMetadata()->add('sources', $sources);
        }

        yield from $this->complete($result, $request, $agentContext);
    }

    /**
     * Consumes a streamed round, forwarding every delta as a progress update.
     *
     * The stream is drained completely even after a tool call was seen, since its metadata (e.g. token
     * usage) is only complete once the underlying generator is exhausted.
     *
     * @return \Generator<int, UpdateInterface, mixed, array{ResultInterface, AssistantMessage}>
     */
    private function consumeStream(StreamResult $stream): \Generator
    {
        $text = '';
        $toolCalls = [];

        foreach ($stream->getContent() as $delta) {
            if ($delta instanceof ToolCallComplete) {
                $toolCalls = [...$toolCalls, ...$delta->getToolCalls()];

                continue;
            }

            if ([] !== $toolCalls) {
                // the model asked for tools, the remaining deltas of this round are not part of the answer
                continue;
            }

            if ($delta instanceof TextDelta) {
                $text .= $delta->getText();
            }

            yield new Progress('delta', 'Received a streamed delta.', $delta);
        }

        $turn = $stream->getAssistantMessage();

        if ([] !== $toolCalls) {
            $result = new ToolCallResult($toolCalls);
        } else {
            // a streamed structured output round ends with the object assembled by the platform's listener
            $result = $this->streamedObjectResult($stream) ?? $this->turnResult($turn, $text);
        }

        $result->getMetadata()->merge($stream->getMetadata());

        return [$result, $turn];
    }

    /**
     * The streamed turn as the result the same round would have returned unstreamed, so a thinking
     * block and its signature survive into the next turn.
     */
    private function turnResult(AssistantMessage $turn, string $text): ResultInterface
    {
        $parts = [];
        foreach ($turn->getContent() as $content) {
            if ($content instanceof Thinking) {
                $parts[] = new ThinkingResult($content->getContent(), $content->getSignature());
            }

            if ($content instanceof Text) {
                $parts[] = new TextResult($content->getText(), $content->getSignature());
            }
        }

        if ([] === $parts) {
            return new TextResult($text);
        }

        if (1 === \count($parts) && $parts[0] instanceof TextResult) {
            return $parts[0];
        }

        return new MultiPartResult($parts);
    }

    private function streamedObjectResult(StreamResult $stream): ?ObjectResult
    {
        foreach ($stream->getListeners() as $listener) {
            if ($listener instanceof PartialObjectStreamListener) {
                return $listener->getFinalObjectResult();
            }
        }

        return null;
    }

    /**
     * Runs the result-aware processors and yields the final result.
     *
     * @return \Generator<int, UpdateInterface, mixed, void>
     */
    private function complete(ResultInterface $result, AgentRequest $request, AgentContext $agentContext): \Generator
    {
        $agentResult = new AgentResult($request->getModel(), $result, $request->getMessageBag(), $request->getOptions(), $request->getContext());

        foreach ($this->applicableProcessors($request->getContext()) as $processor) {
            if (!$processor instanceof ResultAwareContextProcessorInterface) {
                continue;
            }

            $processor->processResult($agentResult, $agentContext);
            yield from $agentContext->flushUpdates();
        }

        yield new ResultUpdate($agentResult->getResult());
    }

    /**
     * A processor without supported types is global and always runs, otherwise it only runs when the context
     * carries at least one item of a type it supports.
     *
     * @return list<ContextProcessorInterface>
     */
    private function applicableProcessors(Context $context): array
    {
        $applicable = [];

        foreach ($this->contextProcessors as $processor) {
            $types = $processor::supportedTypes();
            if ([] === $types) {
                $applicable[] = $processor;

                continue;
            }

            foreach ($types as $type) {
                if ($context->has($type)) {
                    $applicable[] = $processor;

                    continue 2;
                }
            }
        }

        return $applicable;
    }

    private function extractToolCallResult(ResultInterface $result): ?ToolCallResult
    {
        if ($result instanceof ToolCallResult) {
            return $result;
        }

        if ($result instanceof MultiPartResult) {
            return $result->asToolCallResult();
        }

        return null;
    }
}
