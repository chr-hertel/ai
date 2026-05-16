<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\MultiAgent;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Exception\ExceptionInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\MultiAgent\Handoff\Decision;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * A multi-agent system that coordinates multiple specialized agents.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @deprecated since Symfony AI 1.0, configure handoffs on the Agent constructor instead.
 */
final class MultiAgent implements AgentInterface
{
    /**
     * @param Handoff[]        $handoffs Handoff definitions for agent routing
     * @param non-empty-string $name     Name of the multi-agent
     */
    public function __construct(
        private AgentInterface $orchestrator,
        private array $handoffs,
        private AgentInterface $fallback,
        private string $name = 'multi-agent',
        private LoggerInterface $logger = new NullLogger(),
    ) {
        if ([] === $handoffs) {
            throw new InvalidArgumentException('MultiAgent requires at least 1 handoff.');
        }
    }

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @throws ExceptionInterface When the agent encounters an error during orchestration or handoffs
     */
    public function call(string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): ResultInterface
    {
        $messages = $this->normalizeInput($input);
        $userMessages = $messages->withoutSystemMessage();

        $userMessage = $userMessages->getUserMessage();
        if (null === $userMessage) {
            throw new RuntimeException('No user message found in conversation.');
        }
        $userText = $userMessage->asText();
        $this->logger->debug('MultiAgent: Processing user message', ['user_text' => $userText]);

        $this->logger->debug('MultiAgent: Available agents for routing', ['agents' => array_map(static fn (Handoff $handoff): array => [
            'to' => $handoff->getTo()->getName(),
            'when' => $handoff->getWhen(),
        ], $this->handoffs)]);

        $agentSelectionPrompt = $this->buildAgentSelectionPrompt($userText ?? '');

        $decision = $this->orchestrator->call(
            new MessageBag(Message::ofUser($agentSelectionPrompt)),
            options: array_merge($options, ['response_format' => Decision::class]),
        )->getContent();

        if (!$decision instanceof Decision) {
            $this->logger->debug('MultiAgent: Failed to get decision, falling back to orchestrator');

            return $this->orchestrator->call($messages, options: $options);
        }

        $this->logger->debug('MultiAgent: Agent selection completed', [
            'selected_agent' => $decision->getAgentName(),
            'reasoning' => $decision->getReasoning(),
        ]);

        if (!$decision->hasAgent()) {
            $this->logger->debug('MultiAgent: Using fallback agent', ['reason' => 'no_agent_selected']);

            return $this->fallback->call($messages, options: $options);
        }

        $targetAgent = null;
        foreach ($this->handoffs as $handoff) {
            if ($handoff->getTo()->getName() === $decision->getAgentName()) {
                $targetAgent = $handoff->getTo();
                break;
            }
        }

        if (null === $targetAgent) {
            $this->logger->debug('MultiAgent: Target agent not found, using fallback agent', [
                'requested_agent' => $decision->getAgentName(),
            ]);

            return $this->fallback->call($messages, options: $options);
        }

        $this->logger->debug('MultiAgent: Delegating to agent', ['agent_name' => $decision->getAgentName()]);

        return $targetAgent->call(new MessageBag($userMessage), options: $options);
    }

    public function run(string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): Execution
    {
        return new Execution(function () use ($input, $context, $options): \Generator {
            yield new ResultUpdate($this->call($input, $context, $options));
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

    private function buildAgentSelectionPrompt(string $userQuestion): string
    {
        $agentDescriptions = [];
        $agentNames = [];

        foreach ($this->handoffs as $handoff) {
            $triggers = implode(', ', $handoff->getWhen());
            $agentName = $handoff->getTo()->getName();
            $agentDescriptions[] = "- {$agentName}: {$triggers}";
            $agentNames[] = $agentName;
        }

        $agentDescriptions[] = "- {$this->fallback->getName()}: fallback agent for general/unmatched queries";
        $agentNames[] = $this->fallback->getName();

        $agentList = implode("\n", $agentDescriptions);
        $validAgents = implode('", "', $agentNames);

        return <<<PROMPT
            You are an intelligent agent orchestrator. Based on the user's question, determine which specialized agent should handle the request.

            User question: "{$userQuestion}"

            Available agents and their capabilities:
            {$agentList}

            Analyze the user's question and select the most appropriate agent to handle this request.
            Return an empty string ("") for agentName if no specific agent matches the request criteria.

            Available agent names: {$validAgents}

            Provide your selection and explain your reasoning.
            PROMPT;
    }
}
