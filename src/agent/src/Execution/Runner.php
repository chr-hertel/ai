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

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsRequested;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Commentary;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\CommentaryResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialObjectStreamListener;
use Symfony\AI\Platform\Tool\Tool;
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
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly ?ToolboxInterface $toolbox = null,
        private readonly ?ToolExecutorInterface $toolExecutor = null,
        private readonly ?int $maxToolCalls = 50,
        private readonly bool $includeSources = false,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ToolResultConverter $resultConverter = new ToolResultConverter(),
    ) {
    }

    /**
     * @param non-empty-string|Model            $model
     * @param array<string, mixed>              $options
     * @param (\Closure(MessageBag): void)|null $onToolRound receives the messages after each completed tool round
     *
     * @return \Generator<int, UpdateInterface, mixed, void>
     */
    public function run(string|Model $model, MessageBag $messages, array $options, ?Cancellation $cancellation = null, ?\Closure $onToolRound = null): \Generator
    {
        [$options, $resolutions] = $this->extractResolutions($options);
        [$options, $allowedTools] = $this->exposeTools($options);

        $sources = new SourceCollection();
        $metadata = new Metadata();
        $iterations = 0;
        $suspended = false;

        if (null !== $resolutions) {
            [$messages, $sources] = yield from $this->resolve($messages, $resolutions, $allowedTools, $sources);

            if (null !== $onToolRound) {
                $onToolRound($messages);
            }
        }

        while (true) {
            $deferredResult = $this->platform->invoke($model, $messages, $options);
            $cancellation?->activate($deferredResult->getRawResult());

            try {
                yield new Progress(Progress::STAGE_MODEL_REQUEST, 'Invoking model.', $model);

                if ($cancellation?->isRequested()) {
                    return;
                }

                $result = $deferredResult->getResult();

                $assistantMessage = null;
                if ($result instanceof StreamResult) {
                    $streamedResult = yield from $this->consumeStream($result, $cancellation);
                    if (null === $streamedResult) {
                        return;
                    }

                    [$result, $assistantMessage] = $streamedResult;
                }
            } finally {
                $cancellation?->deactivate();
            }

            if ($cancellation?->isRequested()) {
                return;
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
            $this->denyRestrictedToolCalls($toolCalls, $allowedTools);

            $requested = new ToolCallsRequested($toolCalls, $this->definitions(), $messages);
            $this->eventDispatcher?->dispatch($requested);
            $executable = array_values(array_filter($toolCalls, static fn (ToolCall $toolCall): bool => !$requested->isSuspended($toolCall)));

            $toolResults = [];
            if ([] !== $executable) {
                $toolResults = yield from $this->toolExecutor->execute($executable);
            }

            $messages = $messages->with($this->toAssistantMessage($result, $assistantMessage, $result->getMetadata()->all()));
            foreach ($toolResults as $i => $toolResult) {
                [$messages, $sources] = $this->answer($messages, $sources, $executable[$i], $toolResult);
            }

            if (null !== $onToolRound) {
                $onToolRound($messages);
            }

            $suspendedToolCalls = $requested->getSuspendedToolCalls();
            if ([] !== $suspendedToolCalls) {
                yield new Progress(Progress::STAGE_SUSPENSION, 'Waiting for the suspended tool calls to be resolved.', $suspendedToolCalls);

                // the unanswered calls are the result, the assistant message asking for them is already part of the messages
                $result = new ToolCallResult($suspendedToolCalls);
                $result->getMetadata()->add('suspensions', $requested->getSuspensionReasons());
                $suspended = true;

                break;
            }

            $event = new ToolCallsExecuted($toolResults);
            $this->eventDispatcher?->dispatch($event);

            if ($event->hasResult()) {
                $result = $event->getResult();
                $assistantMessage = null;

                break;
            }
        }

        $roundMetadata = $result->getMetadata()->all();
        if ($this->includeSources) {
            $roundMetadata['sources'] = $sources;
        }
        $answer = fn (?ResultInterface $replacement = null): MessageBag => $messages->with(null === $replacement
            ? $this->toAssistantMessage($result, $assistantMessage, $roundMetadata)
            : $this->toAssistantMessage($replacement, null, $roundMetadata));

        if ($suspended) {
            $answer = static fn (?ResultInterface $replacement = null): MessageBag => $messages;
        }

        // Merged in order, so mergeable values like the token usage add up and the final result's own values win
        $metadata->merge($result->getMetadata());
        $result->getMetadata()->set($metadata->all());

        if ($this->includeSources) {
            $result->getMetadata()->add('sources', $sources);
        }

        yield new ResultUpdate($result, $answer);
    }

    /**
     * Answers the tool calls a suspended execution left unanswered at the end of the conversation.
     *
     * @param array<string, ToolCallResolution> $resolutions  keyed by tool call id
     * @param list<string>|null                 $allowedTools
     *
     * @return \Generator<int, UpdateInterface, mixed, array{MessageBag, SourceCollection}>
     */
    private function resolve(MessageBag $messages, array $resolutions, ?array $allowedTools, SourceCollection $sources): \Generator
    {
        $pending = $this->unansweredToolCalls($messages);

        foreach (array_keys($resolutions) as $id) {
            if (!isset($pending[$id])) {
                throw new InvalidArgumentException(\sprintf('The conversation has no unanswered tool call with id "%s".', $id));
            }
        }

        $executions = [];
        foreach ($pending as $id => $toolCall) {
            if (!isset($resolutions[$id])) {
                throw new InvalidArgumentException(\sprintf('The unanswered call of tool "%s" with id "%s" has no resolution.', $toolCall->getName(), $id));
            }

            if ($resolutions[$id]->isExecution()) {
                $executions[$id] = new ToolCall($toolCall->getId(), $toolCall->getName(), $resolutions[$id]->getArguments() ?? $toolCall->getArguments(), $toolCall->getSignature());
            }
        }

        $toolResults = [];
        if ([] !== $executions) {
            if (null === $this->toolExecutor) {
                throw new InvalidArgumentException('Executing a resolved tool call requires a toolbox.');
            }

            $this->denyRestrictedToolCalls(array_values($executions), $allowedTools);
            $toolResults = array_combine(array_keys($executions), yield from $this->toolExecutor->execute(array_values($executions)));
        }

        foreach ($pending as $id => $toolCall) {
            if (isset($toolResults[$id])) {
                [$messages, $sources] = $this->answer($messages, $sources, $executions[$id], $toolResults[$id]);

                continue;
            }

            $messages = $messages->with(Message::ofToolCall($toolCall, $resolutions[$id]->getAnswer() ?? ''));
        }

        return [$messages, $sources];
    }

    /**
     * @return array{MessageBag, SourceCollection}
     */
    private function answer(MessageBag $messages, SourceCollection $sources, ToolCall $toolCall, ToolResult $toolResult): array
    {
        // a tool returning nothing still has to answer its call
        $messages = $messages->with(Message::ofToolCall($toolCall, $this->resultConverter->convert($toolResult) ?? ''));

        if (null !== $toolResult->getSources()) {
            $sources = $sources->merge($toolResult->getSources());
        }

        return [$messages, $sources];
    }

    /**
     * The tool calls of the last assistant message without an answer, as long as only tool messages follow it.
     *
     * @return array<string, ToolCall> keyed by tool call id
     */
    private function unansweredToolCalls(MessageBag $messages): array
    {
        $answered = [];
        foreach (array_reverse($messages->getMessages()) as $message) {
            if ($message instanceof ToolCallMessage) {
                $answered[$message->getToolCall()->getId()] = true;

                continue;
            }

            if (!$message instanceof AssistantMessage) {
                return [];
            }

            $pending = [];
            foreach ($message->getToolCalls() as $toolCall) {
                if (!isset($answered[$toolCall->getId()])) {
                    $pending[$toolCall->getId()] = $toolCall;
                }
            }

            return $pending;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{array<string, mixed>, array<string, ToolCallResolution>|null}
     */
    private function extractResolutions(array $options): array
    {
        if (!\array_key_exists('tool_call_resolutions', $options)) {
            return [$options, null];
        }

        $resolutions = $options['tool_call_resolutions'];
        unset($options['tool_call_resolutions']);

        if (!\is_array($resolutions)) {
            throw new InvalidArgumentException(\sprintf('The "tool_call_resolutions" option must be an array of "%s" keyed by tool call id.', ToolCallResolution::class));
        }

        foreach ($resolutions as $resolution) {
            if (!$resolution instanceof ToolCallResolution) {
                throw new InvalidArgumentException(\sprintf('The "tool_call_resolutions" option must be an array of "%s" keyed by tool call id.', ToolCallResolution::class));
            }
        }

        return [$options, $resolutions];
    }

    /**
     * @return array<string, Tool> keyed by tool name
     */
    private function definitions(): array
    {
        if (!$this->toolbox instanceof ToolboxInterface) {
            return [];
        }

        $definitions = [];
        foreach ($this->toolbox->getTools() as $tool) {
            $definitions[$tool->getName()] = $tool;
        }

        return $definitions;
    }

    /**
     * The round's answer as assistant message, carrying the metadata of that round only, like its token usage.
     *
     * @param array<string, mixed> $metadata
     */
    private function toAssistantMessage(ResultInterface $result, ?AssistantMessage $streamedMessage, array $metadata): AssistantMessage
    {
        $message = $streamedMessage ?? Message::ofAssistant($result);
        $message->getMetadata()->set($metadata);

        return $message;
    }

    /**
     * Consumes a streamed round, forwarding every delta as a progress update.
     *
     * The stream is drained completely even after a tool call was seen, since its metadata (e.g. token
     * usage) is only complete once the underlying generator is exhausted.
     *
     * @return \Generator<int, UpdateInterface, mixed, array{ResultInterface, AssistantMessage}|null>
     */
    private function consumeStream(StreamResult $stream, ?Cancellation $cancellation): \Generator
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

            yield new Progress(Progress::STAGE_DELTA, 'Received a streamed delta.', $delta);

            if ($cancellation?->isRequested()) {
                return null;
            }
        }

        if ($cancellation?->isRequested()) {
            return null;
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

            if ($content instanceof Commentary) {
                $parts[] = new CommentaryResult($content->getContent());
            }

            if ($content instanceof Text) {
                $parts[] = new TextResult($content->getText(), $content->getSignature(), $content->getPhase());
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
     * Exposes the registered tools, narrowed down by the tool names given in the tools option.
     *
     * @param array<string, mixed> $options
     *
     * @return array{array<string, mixed>, list<string>|null} the options and the names of the tools allowed to be executed, null if unrestricted
     */
    private function exposeTools(array $options): array
    {
        $allowedTools = null;
        $serverTools = [];

        if (isset($options['tools']) && \is_array($options['tools'])) {
            $names = array_values(array_filter($options['tools'], static fn (mixed $tool): bool => \is_string($tool)));
            $serverTools = array_values(array_filter($options['tools'], static fn (mixed $tool): bool => \is_array($tool)));

            // only restrict tools if tool names are provided as option, an empty option allows no tool at all
            if ([] !== $names || [] === $options['tools']) {
                $allowedTools = $names;
            }
        }

        if (!$this->toolbox instanceof ToolboxInterface) {
            return [$options, $allowedTools];
        }

        $toolMap = $this->toolbox->getTools();
        if ([] === $toolMap) {
            return [$options, $allowedTools];
        }

        if (null !== $allowedTools) {
            $toolMap = array_values(array_filter($toolMap, static fn (Tool $tool): bool => \in_array($tool->getName(), $allowedTools, true)));
        }

        $options['tools'] = [...$toolMap, ...$serverTools];

        return [$options, $allowedTools];
    }

    /**
     * Rejects calls of registered tools the tools option did not allow, unknown names are left to the toolbox.
     *
     * @param list<ToolCall>    $toolCalls
     * @param list<string>|null $allowedTools
     */
    private function denyRestrictedToolCalls(array $toolCalls, ?array $allowedTools): void
    {
        if (null === $allowedTools || !$this->toolbox instanceof ToolboxInterface) {
            return;
        }

        // listed again, since the tools of a toolbox can change during a run
        $registeredTools = array_map(static fn (Tool $tool): string => $tool->getName(), $this->toolbox->getTools());

        foreach ($toolCalls as $toolCall) {
            if (\in_array($toolCall->getName(), $registeredTools, true) && !\in_array($toolCall->getName(), $allowedTools, true)) {
                throw ToolNotFoundException::notFoundForToolCall($toolCall);
            }
        }
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
