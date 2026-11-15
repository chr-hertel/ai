<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decision\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;

final class EvaluationTest extends TestCase
{
    public function testItExposesStateAndQuestions()
    {
        $questions = [
            'is_urgent' => new NoulQuestion('Does this convey urgency?'),
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payments', 'technical' => null]),
        ];

        $evaluation = new Evaluation('My payouts have been failing for 3 days.', $questions);

        $this->assertSame('My payouts have been failing for 3 days.', $evaluation->getState());
        $this->assertSame($questions, $evaluation->getQuestions());
        $this->assertSame([], $evaluation->getImages());
    }

    public function testItAcceptsStructuredState()
    {
        $state = [['role' => 'customer', 'text' => 'Where is my refund?']];

        $evaluation = new Evaluation($state, ['is_urgent' => new NoulQuestion('Does this convey urgency?')]);

        $this->assertSame($state, $evaluation->getState());
    }

    public function testItThrowsExceptionWithoutQuestions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An evaluation requires at least one question.');

        new Evaluation('state', []);
    }

    public function testItThrowsExceptionForInvalidQuestion()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question "is_urgent" must be an instance of');

        /* @phpstan-ignore argument.type */
        new Evaluation('state', ['is_urgent' => 'Does this convey urgency?']);
    }

    public function testItKeepsImagesInOrder()
    {
        $images = [new Image('first image', 'image/png'), new Image('second image', 'image/jpeg')];

        $evaluation = new Evaluation('A yearbook page.', ['photo' => new NoulQuestion('Is this a photograph?')], $images);

        $this->assertSame($images, $evaluation->getImages());
    }

    public function testItRejectsImagesThatAreNotImageObjects()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Evaluation images must be instances of');

        /* @phpstan-ignore argument.type */
        new Evaluation('text', ['q' => new NoulQuestion('Relevant?')], images: ['https://example.com/image.png']);
    }
}
