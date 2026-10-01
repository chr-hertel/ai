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

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Agent\Execution\Turn;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * Runs an agent and records what happened, based on the turns its execution reports.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RunRecorder
{
    /**
     * @param array<string, mixed> $options
     */
    public static function record(AgentInterface $agent, string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): RecordedRun
    {
        return (new self())->run($agent, $input, $context, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function run(AgentInterface $agent, string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): RecordedRun
    {
        $messages = match (true) {
            $input instanceof MessageBag => $input,
            $input instanceof UserMessage => new MessageBag($input),
            default => new MessageBag(Message::ofUser($input)),
        };
        $inputMessages = clone $messages;

        $turns = [];
        $result = null;
        $error = null;
        $startedAt = hrtime(true);

        try {
            foreach ($agent->call($messages, $context, $options) as $update) {
                if ($update instanceof Progress && $update->getPayload() instanceof Turn) {
                    $turns[] = $update->getPayload();
                }

                if ($update instanceof Result) {
                    $result = $update->getResult();
                }
            }
        } catch (\Throwable $e) {
            $error = $e;
        }

        $duration = (hrtime(true) - $startedAt) / 1e9;

        return new RecordedRun(
            $inputMessages,
            $result,
            $this->toolCalls($turns),
            $turns,
            $this->tokenUsage($result),
            $duration,
            $this->runContext($result, $context),
            $error,
        );
    }

    /**
     * @param list<Turn> $turns
     *
     * @return list<RecordedToolCall>
     */
    private function toolCalls(array $turns): array
    {
        $toolCalls = [];
        foreach ($turns as $turn) {
            foreach ($turn->getToolResults() as $toolResult) {
                $toolCall = $toolResult->getToolCall();
                $toolCalls[] = new RecordedToolCall($toolCall->getName(), $toolCall->getArguments(), $toolResult->getResult(), $toolResult->isFailure());
            }
        }

        return $toolCalls;
    }

    private function tokenUsage(?ResultInterface $result): ?TokenUsageInterface
    {
        $usage = $result?->getMetadata()->get('token_usage');

        return $usage instanceof TokenUsageInterface ? $usage : null;
    }

    private function runContext(?ResultInterface $result, Context $context): ?RunContext
    {
        $runContext = $result?->getMetadata()->get('run_context');

        return $runContext instanceof RunContext ? $runContext : $context->get(RunContext::class);
    }
}
