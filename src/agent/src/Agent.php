<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Agent\Execution\Runner;
use Symfony\AI\Agent\Execution\Turn;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Agent\Run\RunContext;
use Symfony\AI\Agent\Run\RunContextAwareInterface;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Agent implements AgentInterface
{
    private readonly Runner $runner;
    private readonly ?ToolboxInterface $toolbox;
    private readonly ?ToolExecutorInterface $toolExecutor;

    /**
     * @param InputProcessorInterface[]  $inputProcessors
     * @param OutputProcessorInterface[] $outputProcessors
     * @param non-empty-string|Model     $model
     * @param bool                       $includeSources   exposes the sources collected during tool calling as `sources` result metadata
     * @param MessageStoreInterface|null $messageStore     continues the conversation stored under the id of the given messages and saves its progress
     */
    public function __construct(
        PlatformInterface $platform,
        private readonly string|Model $model,
        private readonly iterable $inputProcessors = [],
        private readonly iterable $outputProcessors = [],
        private readonly string $name = 'agent',
        ?ToolboxInterface $toolbox = null,
        ?ToolExecutorInterface $toolExecutor = null,
        ?int $maxToolCalls = 50,
        bool $includeSources = false,
        ?EventDispatcherInterface $eventDispatcher = null,
        ToolResultConverter $toolResultConverter = new ToolResultConverter(),
        private readonly ?MessageStoreInterface $messageStore = null,
    ) {
        if (null === $toolExecutor && $toolbox instanceof ToolboxInterface) {
            $toolExecutor = new SequentialToolExecutor($toolbox);
        }

        $this->toolbox = $toolbox;
        $this->toolExecutor = $toolExecutor;

        $this->runner = new Runner(
            $platform,
            $toolbox,
            $toolExecutor,
            $maxToolCalls,
            $includeSources,
            $eventDispatcher,
            $toolResultConverter,
        );
    }

    /**
     * @return non-empty-string|Model
     */
    public function getModel(): string|Model
    {
        return $this->model;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Starts the agent and returns a lazy {@see ExecutionInterface} that is also the result it produces.
     *
     * Read it eagerly with `->getContent()`/`->getResult()`, iterate it to observe every model request, tool call
     * and streamed delta as an update, or register callbacks via `->onProgress(...)`. With the "stream" option
     * set, `->getContent()` yields the answer's deltas.
     *
     * @param array<string, mixed> $options
     *
     * @throws InvalidArgumentException When the platform returns a client error (4xx) indicating invalid request parameters
     * @throws RuntimeException         When the platform returns a server error (5xx) or network failure occurs
     * @throws ExceptionInterface       When the platform converter throws an exception
     */
    public function call(string|MessageBag|UserMessage $input, array $options = []): ExecutionInterface
    {
        $runContext = $options['run_context'] ?? new RunContext();
        if (!$runContext instanceof RunContext) {
            throw new InvalidArgumentException(\sprintf('The "run_context" option must be an instance of "%s", "%s" given.', RunContext::class, get_debug_type($runContext)));
        }
        unset($options['run_context']);

        $id = Uuid::v7();
        if ('' === $runContext->getRunId()) {
            $runContext = $runContext->withRunId($id->toRfc4122());
        }

        $cancellation = new Cancellation();
        $factory = function () use ($input, $options, $cancellation, $runContext): \Generator {
            $input = InputNormalizer::toMessageBag($input);
            if (null !== $this->messageStore) {
                $input = $this->continueStored($input, $this->messageStore);
                $this->messageStore->save($input);
            }

            $request = new Input($this->getModel(), $input, $options, $runContext);
            $updates = $this->run($input, $request, $cancellation);

            // injected before every step, since executions of the same agent can interleave
            $this->injectRunContext($request->getRunContext());
            while ($updates->valid()) {
                yield $updates->current();

                $this->injectRunContext($request->getRunContext());
                $updates->next();
            }
        };

        return new Execution($factory, true === ($options['stream'] ?? false), $cancellation, $id);
    }

    /**
     * @return \Generator<int, UpdateInterface, mixed, void>
     */
    private function run(MessageBag $input, Input $request, Cancellation $cancellation): \Generator
    {
        foreach ($this->inputProcessors as $inputProcessor) {
            if (!$inputProcessor instanceof InputProcessorInterface) {
                throw new InvalidArgumentException(\sprintf('Input processor "%s" must implement "%s".', $inputProcessor::class, InputProcessorInterface::class));
            }

            if ($inputProcessor instanceof AgentAwareInterface) {
                $inputProcessor->setAgent($this);
            }

            $inputProcessor->processInput($request);
        }

        $model = $request->getModel();
        $messages = $request->getMessageBag();
        $processedOptions = $request->getOptions();

        $result = null;
        $resultUpdate = null;
        $turns = [];
        $onToolRound = null;
        if (null !== $this->messageStore) {
            $store = $this->messageStore;
            $onToolRound = static fn (MessageBag $round) => $store->save($input->merge(new MessageBag(...\array_slice($round->getMessages(), \count($messages)))));
        }

        foreach ($this->runner->run($model, $messages, $processedOptions, $cancellation, $onToolRound, $request->getRunContext()) as $update) {
            if ($update instanceof ResultUpdate) {
                $result = $update->getResult();
                $resultUpdate = $update;

                continue;
            }

            if ($update instanceof Progress && $update->getPayload() instanceof Turn) {
                $turns[] = $update->getPayload();
            }

            yield $update;
        }

        if ($cancellation->isRequested()) {
            return;
        }

        \assert($result instanceof ResultInterface && $resultUpdate instanceof ResultUpdate);

        $runContext = $request->getRunContext();
        $output = new Output($model, $result, $messages, $processedOptions, $runContext, $turns);
        foreach ($this->outputProcessors as $outputProcessor) {
            if (!$outputProcessor instanceof OutputProcessorInterface) {
                throw new InvalidArgumentException(\sprintf('Output processor "%s" must implement "%s".', $outputProcessor::class, OutputProcessorInterface::class));
            }

            if ($outputProcessor instanceof AgentAwareInterface) {
                $outputProcessor->setAgent($this);
            }

            $outputProcessor->processOutput($output);
        }

        $finalResult = $output->getResult();
        $conversation = static function () use ($resultUpdate, $messages, $input, $result, $finalResult): MessageBag {
            $runMessages = $resultUpdate->getMessages($finalResult !== $result ? $finalResult : null);

            // the runner appended to the processed request, the caller's input only gets the new messages
            $newMessages = \array_slice($runMessages?->getMessages() ?? [], \count($messages));

            return $input->merge(new MessageBag(...$newMessages));
        };

        if (null !== $this->messageStore) {
            $conversation = $conversation();
            $this->messageStore->save($conversation);
        }

        $finalResult->getMetadata()->add('run_id', $runContext->getRunId());
        $finalResult->getMetadata()->add('run_context', $runContext);

        yield new ResultUpdate($finalResult, $conversation);
    }

    private function injectRunContext(RunContext $runContext): void
    {
        foreach ([...$this->inputProcessors, ...$this->outputProcessors, $this->toolbox, $this->toolExecutor] as $target) {
            if ($target instanceof RunContextAwareInterface) {
                $target->setRunContext($runContext);
            }
        }
    }

    /**
     * The stored conversation followed by the given messages it doesn't contain yet.
     */
    private function continueStored(MessageBag $input, MessageStoreInterface $store): MessageBag
    {
        $stored = $store->load($input->getId());
        $storedIds = array_flip(array_map(static fn (MessageInterface $message): string => $message->getId()->toRfc4122(), $stored->getMessages()));

        $messages = $stored->merge(new MessageBag(...array_filter(
            $input->getMessages(),
            static fn (MessageInterface $message): bool => !isset($storedIds[$message->getId()->toRfc4122()]),
        )));
        $messages->getMetadata()->set(array_replace($stored->getMetadata()->all(), $input->getMetadata()->all()));

        return $messages;
    }
}
