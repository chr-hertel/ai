<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decision;

use Symfony\AI\Platform\Bridge\Decision\Question\QuestionInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;

/**
 * The input of a decision invocation: a state, and the questions to answer about it.
 *
 * A decision model ingests the state once and answers every question against it, with probabilities instead of text.
 * The contract of each platform turns it into the request of its provider.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class Evaluation
{
    /**
     * @param string|array<mixed>                 $state     The content to evaluate, as plain text or structured data
     * @param array<array-key, QuestionInterface> $questions The questions, keyed by the identifier their answer is returned under
     * @param list<Image>                         $images    Images shared by all questions, in request order
     */
    public function __construct(
        private readonly string|array $state,
        private readonly array $questions,
        private readonly array $images = [],
    ) {
        if ([] === $questions) {
            throw new InvalidArgumentException('An evaluation requires at least one question.');
        }

        foreach ($images as $image) {
            if (!$image instanceof Image) {
                throw new InvalidArgumentException(\sprintf('Evaluation images must be instances of "%s", "%s" given.', Image::class, get_debug_type($image)));
            }
        }

        foreach ($questions as $id => $question) {
            if (!$question instanceof QuestionInterface) {
                throw new InvalidArgumentException(\sprintf('Question "%s" must be an instance of "%s", "%s" given.', $id, QuestionInterface::class, get_debug_type($question)));
            }
        }
    }

    /**
     * @return string|array<mixed>
     */
    public function getState(): string|array
    {
        return $this->state;
    }

    /**
     * @return array<array-key, QuestionInterface>
     */
    public function getQuestions(): array
    {
        return $this->questions;
    }

    /**
     * @return list<Image>
     */
    public function getImages(): array
    {
        return array_values($this->images);
    }
}
