<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Evaluator;

use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\EvaluatorInterface;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\RecordedToolCall;
use Symfony\AI\Eval\Score;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Lets a model check that the claims of the final answer are supported by the results of the tools the agent called.
 *
 * Runs without an answer or without a successful tool call have no retrieved context and are skipped.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Faithfulness implements EvaluatorInterface
{
    public const DEFAULT_PROMPT = <<<PROMPT
        You are a strict evaluator of an AI assistant. You receive criteria and the transcript of a conversation,
        including the tool calls the assistant made and their results. List the factual claims of the assistant's
        final answer and check each one against the tool results. A claim is supported only if the tool results state
        or directly imply it, general knowledge does not count. Name the unsupported claims in your reasoning, then
        give the share of supported claims as score between 0.0 (none) and 1.0 (all). An answer without factual
        claims scores 1.0.
        PROMPT;

    private const CRITERIA = 'Every factual claim of the final answer is supported by the tool results.';

    private readonly LlmJudge $judge;

    public function __construct(
        PlatformInterface $platform,
        string $model,
        float $threshold = 0.8,
        string $prompt = self::DEFAULT_PROMPT,
        private readonly string $name = 'faithfulness',
    ) {
        $this->judge = new LlmJudge($platform, $model, $threshold, $prompt, $name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function evaluate(EvalCase $case, RecordedRun $run): ?Score
    {
        if (null === $run->getAnswer() || '' === $run->getAnswer()) {
            return null;
        }

        $retrieved = array_filter($run->getToolCalls(), static fn (RecordedToolCall $call): bool => !$call->isFailed() && null !== $call->getResult());
        if ([] === $retrieved) {
            return null;
        }

        return $this->judge->evaluate(new EvalCase($case->getId(), $case->getInput(), expectations: ['judge' => ['criteria' => self::CRITERIA]]), $run);
    }
}
