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
use Symfony\AI\Eval\Exception\RuntimeException;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\Score;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * Compares the embeddings of the final answer and of the reference answer in "expect.answer" by cosine similarity.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Similarity implements EvaluatorInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
        private readonly float $threshold = 0.8,
        private readonly string $name = 'similarity',
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function evaluate(EvalCase $case, RecordedRun $run): ?Score
    {
        $expected = $case->expectation('answer');
        if (!\is_string($expected) || '' === $expected) {
            return null;
        }

        $answer = $run->getAnswer();
        if (null === $answer || '' === $answer) {
            return Score::fail($this->getName(), 'No answer to compare.');
        }

        $similarity = $this->cosineSimilarity($this->embed($answer), $this->embed($expected));
        $score = max(0.0, min(1.0, $similarity));

        return new Score(
            $this->getName(),
            $score,
            $score >= $this->threshold,
            \sprintf('Cosine similarity of %.3f to the expected answer, %.3f required.', $similarity, $this->threshold),
            ['model' => $this->model],
        );
    }

    private function embed(string $text): Vector
    {
        $vectors = $this->platform->invoke($this->model, $text)->asVectors();
        if (!isset($vectors[0])) {
            throw new RuntimeException(\sprintf('The embeddings model "%s" returned no vector.', $this->model));
        }

        return $vectors[0];
    }

    private function cosineSimilarity(Vector $a, Vector $b): float
    {
        if ($a->getDimensions() !== $b->getDimensions()) {
            throw new RuntimeException(\sprintf('The embeddings model "%s" returned vectors with %d and %d dimensions.', $this->model, $a->getDimensions(), $b->getDimensions()));
        }

        $dot = $normA = $normB = 0.0;
        $data = $b->getData();
        foreach ($a->getData() as $i => $value) {
            $other = $data[$i];
            $dot += $value * $other;
            $normA += $value ** 2;
            $normB += $other ** 2;
        }

        if (0.0 === $normA || 0.0 === $normB) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
