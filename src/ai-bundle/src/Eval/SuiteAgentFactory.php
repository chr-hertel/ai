<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Eval;

use Psr\Container\ContainerInterface;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Agent\Prompt\PromptInputProcessor;
use Symfony\AI\Agent\Prompt\PromptRegistryInterface;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\Variant;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Builds the agent of an eval suite like the configured agent, with the platform, model and prompt version of a variant.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class SuiteAgentFactory
{
    /**
     * @param ContainerInterface                 $platforms        the platforms by service name
     * @param non-empty-string                   $model
     * @param iterable<InputProcessorInterface>  $inputProcessors
     * @param iterable<OutputProcessorInterface> $outputProcessors
     * @param array<string, string|\Stringable>  $promptVariables
     */
    public function __construct(
        private readonly ContainerInterface $platforms,
        private readonly string $platform,
        private readonly string $model,
        private readonly string $name,
        private readonly iterable $inputProcessors = [],
        private readonly iterable $outputProcessors = [],
        private readonly ?ToolboxInterface $toolbox = null,
        private readonly ?int $maxToolCalls = 50,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?PromptRegistryInterface $promptRegistry = null,
        private readonly ?string $promptName = null,
        private readonly array $promptVariables = [],
    ) {
    }

    public function __invoke(Variant $variant, ?ToolboxInterface $fixtureToolbox, EvalCase $case): AgentInterface
    {
        $platform = $this->platforms->get($variant->get('platform', $this->platform));
        \assert($platform instanceof PlatformInterface);

        $model = $variant->get('model', $this->model);
        if (!\is_string($model) || '' === $model) {
            throw new InvalidArgumentException(\sprintf('The model of the variant "%s" must be a non-empty string.', $variant->getName()));
        }

        return new Agent(
            $platform,
            $model,
            $this->inputProcessors($variant),
            $this->outputProcessors,
            $this->name,
            $fixtureToolbox ?? $this->toolbox,
            maxToolCalls: $this->maxToolCalls,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * @return list<InputProcessorInterface>
     */
    private function inputProcessors(Variant $variant): array
    {
        $version = $variant->get('prompt_version');
        if (null === $version) {
            return [...$this->inputProcessors];
        }

        if (null === $this->promptRegistry || null === $this->promptName) {
            throw new InvalidArgumentException(\sprintf('The variant "%s" sets a "prompt_version", but the agent "%s" has no prompt of the registry.', $variant->getName(), $this->name));
        }

        $processors = [];
        foreach ($this->inputProcessors as $processor) {
            $processors[] = $processor instanceof PromptInputProcessor
                ? new PromptInputProcessor($this->promptRegistry, $this->promptName, (string) $version, $this->promptVariables)
                : $processor;
        }

        return $processors;
    }
}
