<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Handoff;

/**
 * Builds the agent-selection prompt and resolves a {@see Decision} to a {@see Handoff}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HandoffResolver
{
    /**
     * @param Handoff[] $handoffs
     */
    public function __construct(
        private readonly array $handoffs,
    ) {
    }

    /**
     * @return Handoff[]
     */
    public function getApplicableHandoffs(): array
    {
        return array_values(array_filter($this->handoffs, static fn (Handoff $handoff): bool => $handoff->isApplicable()));
    }

    public function findByName(string $agentName): ?Handoff
    {
        foreach ($this->getApplicableHandoffs() as $handoff) {
            if ($handoff->getTo()->getName() === $agentName) {
                return $handoff;
            }
        }

        return null;
    }

    public function buildPrompt(string $userQuestion): string
    {
        $descriptions = [];
        $names = [];
        foreach ($this->getApplicableHandoffs() as $handoff) {
            $name = $handoff->getTo()->getName();
            $descriptions[] = \sprintf('- %s: %s', $name, $handoff->getDescription());
            $names[] = $name;
        }

        $agentList = implode("\n", $descriptions);
        $validAgents = implode('", "', $names);

        return <<<PROMPT
            You are an intelligent agent orchestrator. Based on the user's question, determine which specialized agent should handle the request.

            User question: "{$userQuestion}"

            Available agents and their capabilities:
            {$agentList}

            Analyze the user's question and select the most appropriate agent to handle this request.
            Return an empty string ("") for agentName if no specific agent matches the request criteria.

            Available agent names: "{$validAgents}"

            Provide your selection and explain your reasoning.
            PROMPT;
    }
}
