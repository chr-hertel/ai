<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Decision;

use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\QuestionInterface;
use Symfony\AI\Platform\Bridge\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Bridge\OpenAi\Decision;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;

/**
 * Normalizes an evaluation into the request body of the OpenAI Decisions API.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EvaluationNormalizer extends ModelContractNormalizer
{
    /**
     * @param Evaluation $data
     *
     * @return array{input: string|list<array<string, mixed>>, questions: list<array<string, mixed>>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $questions = [];
        foreach ($data->getQuestions() as $name => $question) {
            $questions[] = ['name' => (string) $name] + $this->normalizeQuestion($question);
        }

        return [
            'input' => $this->normalizeInput($data, $context[Contract::CONTEXT_MODEL]),
            'questions' => $questions,
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
     * @return string|list<array<string, mixed>>
     */
    private function normalizeInput(Evaluation $evaluation, Model $model): string|array
    {
        $state = $evaluation->getState();
        $images = $evaluation->getImages();

        if (\is_string($state) && [] === $images) {
            return $state;
        }

        if ([] !== $images && !$model->supports(Capability::INPUT_IMAGE)) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" does not support image inputs.', $model->getName()));
        }

        $content = [\is_string($state) ? ['type' => 'input_text', 'text' => $state] : ['type' => 'input_json', 'data' => $state]];
        foreach ($images as $image) {
            $content[] = ['type' => 'input_image', 'image_url' => $image->asDataUrl()];
        }

        return [['role' => 'user', 'content' => $content]];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeQuestion(QuestionInterface $question): array
    {
        return match (true) {
            $question instanceof NoulQuestion => [
                'type' => 'predicate',
                'instructions' => $this->getPredicateInstructions($question),
            ],
            $question instanceof ChoiceQuestion => [
                'type' => 'choice',
                'instructions' => $question->getInstructions(),
                'choices' => array_map(
                    static fn (string|int $value, ?string $description): array => null === $description ? ['value' => (string) $value] : ['value' => (string) $value, 'description' => $description],
                    array_keys($question->getOptions()),
                    $question->getOptions(),
                ),
            ],
            $question instanceof ScoreQuestion => [
                'type' => 'score',
                'instructions' => $question->getInstructions(),
                'levels' => array_map(static fn (string $level): array => ['label' => $level], $question->getLevels()),
            ],
            default => throw new InvalidArgumentException(\sprintf('Unsupported question type "%s".', $question::class)),
        };
    }

    private function getPredicateInstructions(NoulQuestion $question): string
    {
        // Predicates have no criteria, so they become part of the instructions
        $instructions = $question->getInstructions();

        if (null !== $true = $question->getTrue()) {
            $instructions .= "\nTrue means: ".$true;
        }

        if (null !== $false = $question->getFalse()) {
            $instructions .= "\nFalse means: ".$false;
        }

        return $instructions;
    }
}
