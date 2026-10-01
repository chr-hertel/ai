<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval;

use Symfony\AI\Agent\Context\AgentResult;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Agent\Execution\Turn;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * What actually happened during an agent run.
 *
 * The same object is produced by offline evals, online evals and tests, so every evaluator works on all of them.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RecordedRun
{
    /**
     * @param list<RecordedToolCall> $toolCalls
     * @param list<Turn>             $turns
     * @param float                  $duration  seconds
     */
    public function __construct(
        private readonly MessageBag $input,
        private readonly ?ResultInterface $result,
        private readonly array $toolCalls = [],
        private readonly array $turns = [],
        private readonly ?TokenUsageInterface $tokenUsage = null,
        private readonly float $duration = 0.0,
        private readonly ?RunContext $runContext = null,
        private readonly ?\Throwable $error = null,
    ) {
    }

    /**
     * Rebuilds the run from what an agent knows when it completes, e.g. for online evaluation.
     */
    public static function fromAgentResult(AgentResult $agentResult, float $duration = 0.0): self
    {
        $toolCalls = [];
        $pending = [];
        foreach ($agentResult->getMessageBag()->getMessages() as $message) {
            if ($message instanceof AssistantMessage && $message->hasToolCalls()) {
                foreach ($message->getToolCalls() as $toolCall) {
                    $pending[$toolCall->getId()] = $toolCall;
                }
            }

            if ($message instanceof ToolCallMessage && isset($pending[$message->getToolCall()->getId()])) {
                $toolCall = $pending[$message->getToolCall()->getId()];
                $toolCalls[] = new RecordedToolCall($toolCall->getName(), $toolCall->getArguments(), $message->asText());
            }
        }

        $result = $agentResult->getResult();
        $usage = $result->getMetadata()->get('token_usage');

        return new self(
            $agentResult->getMessageBag(),
            $result,
            $toolCalls,
            [],
            $usage instanceof TokenUsageInterface ? $usage : null,
            $duration,
            $agentResult->getContext()->get(RunContext::class),
        );
    }

    public function getInput(): MessageBag
    {
        return $this->input;
    }

    public function getResult(): ?ResultInterface
    {
        return $this->result;
    }

    /**
     * The final answer as text, null if the run failed or did not answer with text.
     */
    public function getAnswer(): ?string
    {
        $content = $this->result?->getContent();

        return \is_string($content) ? $content : null;
    }

    /**
     * @return list<RecordedToolCall>
     */
    public function getToolCalls(?string $name = null): array
    {
        if (null === $name) {
            return $this->toolCalls;
        }

        return array_values(array_filter($this->toolCalls, static fn (RecordedToolCall $call): bool => $call->getName() === $name));
    }

    public function calledTool(string $name): bool
    {
        return [] !== $this->getToolCalls($name);
    }

    /**
     * @return list<string>
     */
    public function toolSequence(): array
    {
        return array_map(static fn (RecordedToolCall $call): string => $call->getName(), $this->toolCalls);
    }

    /**
     * @return list<Turn>
     */
    public function getTurns(): array
    {
        return $this->turns;
    }

    public function getTokenUsage(): ?TokenUsageInterface
    {
        return $this->tokenUsage;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getRunContext(): ?RunContext
    {
        return $this->runContext;
    }

    public function getRunId(): ?string
    {
        return $this->runContext?->getRunId();
    }

    public function getError(): ?\Throwable
    {
        return $this->error;
    }

    public function isFailed(): bool
    {
        return null !== $this->error;
    }
}
