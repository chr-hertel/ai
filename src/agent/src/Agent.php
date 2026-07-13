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
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Runner;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Agent implements AgentInterface
{
    private readonly Runner $runner;

    /**
     * @param InputProcessorInterface[]  $inputProcessors
     * @param OutputProcessorInterface[] $outputProcessors
     * @param non-empty-string           $model
     * @param bool                       $excludeToolMessages keeps the messages appended during tool calling out of the caller's message bag
     * @param bool                       $includeSources      exposes the sources collected during tool calling as `sources` result metadata
     */
    public function __construct(
        PlatformInterface $platform,
        private readonly string $model,
        private readonly iterable $inputProcessors = [],
        private readonly iterable $outputProcessors = [],
        private readonly string $name = 'agent',
        ?ToolboxInterface $toolbox = null,
        ?ToolExecutorInterface $toolExecutor = null,
        ?int $maxToolCalls = 50,
        bool $excludeToolMessages = false,
        bool $includeSources = false,
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        if (null === $toolExecutor && $toolbox instanceof ToolboxInterface) {
            $toolExecutor = new SequentialToolExecutor($toolbox);
        }

        $this->runner = new Runner(
            $platform,
            $toolbox,
            $toolExecutor,
            $maxToolCalls,
            $excludeToolMessages,
            $includeSources,
            $eventDispatcher,
        );
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * When the "stream" option is set, a {@see StreamResult} is returned, whose deltas are produced by the
     * underlying {@see Execution}. Use {@see self::run()} to observe the execution's updates instead.
     *
     * @param array<string, mixed> $options
     *
     * @throws InvalidArgumentException When the platform returns a client error (4xx) indicating invalid request parameters
     * @throws RuntimeException         When the platform returns a server error (5xx) or network failure occurs
     * @throws ExceptionInterface       When the platform converter throws an exception
     */
    public function call(string|MessageBag|UserMessage $input, array $options = []): ResultInterface
    {
        $execution = $this->run($input, $options);

        if (true === ($options['stream'] ?? false)) {
            return $this->stream($execution);
        }

        return $execution->await();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function run(string|MessageBag|UserMessage $input, array $options = []): Execution
    {
        return new Execution(function () use ($input, $options): \Generator {
            $request = new Input($this->getModel(), InputNormalizer::toMessageBag($input), $options);
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
            foreach ($this->runner->run($model, $messages, $processedOptions) as $update) {
                if ($update instanceof ResultUpdate) {
                    $result = $update->getResult();

                    continue;
                }

                yield $update;
            }

            \assert($result instanceof ResultInterface);

            $output = new Output($model, $result, $messages, $processedOptions);
            foreach ($this->outputProcessors as $outputProcessor) {
                if (!$outputProcessor instanceof OutputProcessorInterface) {
                    throw new InvalidArgumentException(\sprintf('Output processor "%s" must implement "%s".', $outputProcessor::class, OutputProcessorInterface::class));
                }

                if ($outputProcessor instanceof AgentAwareInterface) {
                    $outputProcessor->setAgent($this);
                }

                $outputProcessor->processOutput($output);
            }

            yield new ResultUpdate($output->getResult());
        });
    }

    /**
     * Exposes an execution as a stream of deltas, so a streamed call keeps returning a {@see StreamResult}.
     */
    private function stream(Execution $execution): StreamResult
    {
        /** @var StreamResult|null $stream */
        $stream = null;

        $deltas = (static function () use ($execution, &$stream): \Generator {
            foreach ($execution as $update) {
                if ($update instanceof Progress && 'delta' === $update->getStage() && $update->getPayload() instanceof DeltaInterface) {
                    yield $update->getPayload();

                    continue;
                }

                // the final result carries the metadata aggregated over all rounds, e.g. token usage and sources
                if ($update instanceof ResultUpdate && null !== $stream) {
                    $stream->getMetadata()->merge($update->getResult()->getMetadata());
                }
            }
        })();

        return $stream = new StreamResult($deltas);
    }
}
