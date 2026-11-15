<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Decision;

use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\QuestionInterface;
use Symfony\AI\Platform\Bridge\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Bridge\Gemini\Decision;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;

/**
 * Normalizes an evaluation into the request body of the Gemini Decisions API.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EvaluationNormalizer extends ModelContractNormalizer
{
    /**
     * @param Evaluation $data
     *
     * @return array{input: string|list<array<string, string>>, questions: object}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $questions = [];
        foreach ($data->getQuestions() as $id => $question) {
            $questions[$id] = $this->normalizeQuestion($question);
        }

        return [
            'input' => $this->normalizeInput($data, $context[Contract::CONTEXT_MODEL]),
            // A JSON object, even when the identifiers are numeric
            'questions' => (object) $questions,
        ];
    }

    protected function supportedDataClass(): string
    {
        return Evaluation::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Decision;
    }

    /**
     * @return string|list<array<string, string>>
     */
    private function normalizeInput(Evaluation $evaluation, Model $model): string|array
    {
        $state = $evaluation->getState();
        $text = \is_string($state) ? $state : json_encode($state, \JSON_THROW_ON_ERROR);

        if ([] === $images = $evaluation->getImages()) {
            return $text;
        }

        if (!$model->supports(Capability::INPUT_IMAGE)) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" does not support image inputs.', $model->getName()));
        }

        $input = [['type' => 'text', 'text' => $text]];
        foreach ($images as $image) {
            $input[] = ['type' => 'image', 'data' => $image->asBase64(), 'mime_type' => $image->getFormat()];
        }

        return $input;
    }

    /**
     * @return array{type: string, instructions: string, criteria?: object|list<string>}
     */
    private function normalizeQuestion(QuestionInterface $question): array
    {
        return match (true) {
            $question instanceof NoulQuestion => $this->normalizeNoulQuestion($question),
            $question instanceof ChoiceQuestion => [
                'type' => 'choice',
                'instructions' => $question->getInstructions(),
                'criteria' => (object) array_map(static fn (?string $description): string => $description ?? '', $question->getOptions()),
            ],
            $question instanceof ScoreQuestion => [
                'type' => 'score',
                'instructions' => $question->getInstructions(),
                'criteria' => $question->getLevels(),
            ],
            default => throw new InvalidArgumentException(\sprintf('Unsupported question type "%s".', $question::class)),
        };
    }

    /**
     * @return array{type: 'noul', instructions: string, criteria?: object}
     */
    private function normalizeNoulQuestion(NoulQuestion $question): array
    {
        $normalized = [
            'type' => 'noul',
            'instructions' => $question->getInstructions(),
        ];

        $criteria = array_filter(['true_description' => $question->getTrue(), 'false_description' => $question->getFalse()], static fn (?string $criterion): bool => null !== $criterion);
        if ([] !== $criteria) {
            $normalized['criteria'] = (object) $criteria;
        }

        return $normalized;
    }
}
