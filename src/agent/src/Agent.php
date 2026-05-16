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

use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Agent\Context\LegacyProcessorAdapter;
use Symfony\AI\Agent\Context\Processor\AttachmentProcessor;
use Symfony\AI\Agent\Context\Processor\InstructionProcessor;
use Symfony\AI\Agent\Context\Processor\ToolProcessor;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Runner;
use Symfony\AI\Agent\Handoff\HandoffResolver;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\Tool\Subagent;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Agent implements AgentInterface
{
    private readonly Context $context;

    private ?EventDispatcherInterface $eventDispatcher = null;

    private ?Runner $runner = null;

    /**
     * @param non-empty-string                                                                     $name
     * @param iterable<object|AgentInterface|ToolboxInterface>                                     $tools             local tools, subagents, or a pre-built toolbox
     * @param Handoff\Handoff[]                                                                    $handoffs
     * @param non-empty-string|null                                                                $model             default model, overridable per call via the "model" option
     * @param iterable<ContextProcessorInterface|InputProcessorInterface|OutputProcessorInterface> $contextProcessors
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $name = 'agent',
        string|\Stringable|TranslatableInterface|File|null $instruction = null,
        private readonly iterable $tools = [],
        private readonly array $handoffs = [],
        private readonly ?string $model = null,
        Context $context = new Context(),
        private readonly iterable $contextProcessors = [],
        private readonly ?ToolExecutorInterface $toolExecutor = null,
        private readonly ?int $maxToolCalls = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
        $this->context = null !== $instruction ? $context->with(new Instruction($instruction)) : $context;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->runner = null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function call(string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): ResultInterface
    {
        return $this->run($input, $context, $options)->await();
    }

    public function run(string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): Execution
    {
        $messages = $this->normalizeInput($input);
        $mergedContext = $this->context->merge($context);
        $model = $this->resolveModel($options);

        return new Execution(function () use ($model, $messages, $mergedContext, $options): \Generator {
            yield from $this->runner()->run($this, $model, $messages, $mergedContext, $options);
        });
    }

    private function normalizeInput(string|MessageBag|UserMessage $input): MessageBag
    {
        if ($input instanceof MessageBag) {
            return $input;
        }

        if ($input instanceof UserMessage) {
            return new MessageBag($input);
        }

        return new MessageBag(Message::ofUser($input));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return non-empty-string
     */
    private function resolveModel(array $options): string
    {
        if (isset($options['model']) && \is_string($options['model']) && '' !== $options['model']) {
            return $options['model'];
        }

        if (null === $this->model || '' === $this->model) {
            throw new RuntimeException('No model configured for the agent. Pass a "model" to the constructor or via the "model" option.');
        }

        return $this->model;
    }

    private function runner(): Runner
    {
        if (null !== $this->runner) {
            return $this->runner;
        }

        $toolbox = $this->buildToolbox();

        $processors = [new InstructionProcessor($this->translator), new AttachmentProcessor()];
        if ($toolbox instanceof ToolboxInterface) {
            $processors[] = new ToolProcessor($toolbox);
        }

        foreach ($this->contextProcessors as $processor) {
            if ($processor instanceof ContextProcessorInterface) {
                $processors[] = $processor;
            } elseif ($processor instanceof InputProcessorInterface || $processor instanceof OutputProcessorInterface) {
                $processors[] = new LegacyProcessorAdapter($processor);
            } else {
                throw new InvalidArgumentException(\sprintf('Context processor "%s" must implement "%s".', get_debug_type($processor), ContextProcessorInterface::class));
            }
        }

        $toolExecutor = $this->toolExecutor;
        if (null === $toolExecutor && $toolbox instanceof ToolboxInterface) {
            $toolExecutor = new SequentialToolExecutor($toolbox);
        }

        $handoffResolver = [] !== $this->handoffs ? new HandoffResolver($this->handoffs) : null;

        return $this->runner = new Runner(
            $this->platform,
            $processors,
            $toolExecutor,
            $handoffResolver,
            $this->maxToolCalls,
            $this->eventDispatcher,
        );
    }

    private function buildToolbox(): ?ToolboxInterface
    {
        $tools = \is_array($this->tools) ? array_values($this->tools) : iterator_to_array($this->tools, false);
        if ([] === $tools) {
            return null;
        }

        if (1 === \count($tools) && $tools[0] instanceof ToolboxInterface) {
            return $tools[0];
        }

        $wrapped = [];
        foreach ($tools as $tool) {
            if ($tool instanceof ToolboxInterface) {
                throw new InvalidArgumentException('A ToolboxInterface can only be passed as the sole entry of the "tools" argument.');
            }

            $wrapped[] = $tool instanceof AgentInterface ? new Subagent($tool) : $tool;
        }

        return new Toolbox($wrapped, eventDispatcher: $this->eventDispatcher);
    }
}
