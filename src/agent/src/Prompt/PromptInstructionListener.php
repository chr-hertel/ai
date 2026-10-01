<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Prompt;

use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Agent\Event\AgentInvocationStarted;

/**
 * Resolves the instruction of an agent from the prompt registry for every run, so the selected prompt version can
 * differ per run and ends up in the run context.
 *
 * Listens to {@see AgentInvocationStarted}, which is dispatched once the run context exists and before the
 * instruction is rendered.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PromptInstructionListener
{
    /**
     * @param string|null                       $agentName only resolve the instruction of the agent with this name
     * @param string|null                       $version   the configured version, null for the latest
     * @param array<string, string|\Stringable> $variables
     */
    public function __construct(
        private readonly PromptRegistryInterface $registry,
        private readonly string $promptName,
        private readonly ?string $agentName = null,
        private readonly ?string $version = null,
        private readonly array $variables = [],
        private readonly ?PromptVariantSelectorInterface $selector = null,
    ) {
    }

    public function __invoke(AgentInvocationStarted $event): void
    {
        if (null !== $this->agentName && $event->getAgent()->getName() !== $this->agentName) {
            return;
        }

        $request = $event->getRequest();
        $runContext = $request->getContext()->get(RunContext::class);

        $version = null !== $runContext ? $this->selector?->select($this->promptName, $runContext) : null;
        $prompt = $this->registry->get($this->promptName, $version ?? $this->version);

        $request->setContext($request->getContext()->without(Instruction::class)->with($prompt->toInstruction($this->variables)));
    }
}
