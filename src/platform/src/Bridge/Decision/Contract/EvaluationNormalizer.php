<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decision\Contract;

use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\QuestionInterface;
use Symfony\AI\Platform\Bridge\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Model;

/**
 * Normalizes an evaluation into the request body that TypeSafe, Ollama and OpenRouter decision models share.
 *
 * Platforms with another format, like Gemini or OpenAI, register a normalizer of their own in their contract.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class EvaluationNormalizer extends ModelContractNormalizer
{
    /**
     * @param Evaluation $data
     *
     * @return array{state: string|array<mixed>, questions: object, images?: list<string>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $questions = [];
        foreach ($data->getQuestions() as $id => $question) {
            $questions[$id] = $this->normalizeQuestion($question);
        }

        $payload = [
            'state' => $data->getState(),
            // A JSON object, even when the identifiers are numeric
            'questions' => (object) $questions,
        ];

        if ([] !== $images = $data->getImages()) {
            $model = $context[Contract::CONTEXT_MODEL];
            \assert($model instanceof Model);

            if (!$model->supports(Capability::INPUT_IMAGE)) {
                throw new InvalidArgumentException(\sprintf('The decision model "%s" does not support image inputs.', $model->getName()));
            }

            $payload['images'] = array_map(static fn (Image $image): string => $image->asBase64(), $images);
        }

        return $payload;
    }

    protected function supportedDataClass(): string
    {
        return Evaluation::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return true;
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
                // A JSON object, even when the options are numeric
                'criteria' => (object) $question->getOptions(),
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

        $criteria = array_filter(['true' => $question->getTrue(), 'false' => $question->getFalse()], static fn (?string $criterion): bool => null !== $criterion);
        if ([] !== $criteria) {
            $normalized['criteria'] = (object) $criteria;
        }

        return $normalized;
    }
}
