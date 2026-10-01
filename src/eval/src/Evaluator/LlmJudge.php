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
use Symfony\AI\Eval\Score;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Lets a model grade the run against the free-form criteria in "expect.judge.criteria".
 *
 * Use a stronger model than the one under test, and calibrate the judge against human labels before trusting it.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LlmJudge implements EvaluatorInterface
{
    public const DEFAULT_PROMPT = <<<PROMPT
        You are a strict evaluator of a customer support assistant. You receive criteria and the transcript of a
        conversation, including the tool calls the assistant made. Reason briefly about whether the assistant's final
        answer and behavior meet the criteria, then give a score between 0.0 (fails) and 1.0 (fully meets).
        PROMPT;

    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
        private readonly float $threshold = 0.7,
        private readonly string $prompt = self::DEFAULT_PROMPT,
        private readonly string $name = 'llm_judge',
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function evaluate(EvalCase $case, RecordedRun $run): ?Score
    {
        $criteria = $case->expectation('judge.criteria');
        if (!\is_string($criteria) || '' === $criteria) {
            return null;
        }

        $verdict = $this->platform->invoke($this->model, new MessageBag(
            Message::forSystem($this->prompt),
            Message::ofUser(\sprintf("Criteria:\n%s\n\nTranscript:\n%s", $criteria, TranscriptFormatter::format($run))),
        ), ['response_format' => Verdict::class])->asObject();

        \assert($verdict instanceof Verdict);
        $score = max(0.0, min(1.0, $verdict->score));

        return new Score($this->getName(), $score, $score >= $this->threshold, $verdict->reasoning, ['model' => $this->model]);
    }
}
