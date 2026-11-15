<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Bridge\OpenAi\Decision;
use Symfony\AI\Platform\Bridge\OpenAi\Decision\EvaluationNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;

final class EvaluationNormalizerTest extends TestCase
{
    public function testItSupportsEvaluationsForDecisionModelsOnly()
    {
        $normalizer = new EvaluationNormalizer();
        $evaluation = new Evaluation('state', ['is_urgent' => new NoulQuestion('Urgent?')]);

        $this->assertTrue($normalizer->supportsNormalization($evaluation, context: [Contract::CONTEXT_MODEL => new Decision('gpt-6-luna')]));
        $this->assertFalse($normalizer->supportsNormalization($evaluation, context: [Contract::CONTEXT_MODEL => new Gpt('gpt-6-luna')]));
    }

    public function testItNormalizesQuestionsIntoANamedList()
    {
        $payload = $this->normalize(new Evaluation('My payouts have been failing for 3 days.', [
            'is_bug' => new NoulQuestion('Is this a software defect?'),
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payments', 'technical' => null]),
            'frustration' => new ScoreQuestion('How frustrated is the customer?', ['Calm', 'Very angry']),
        ]));

        $this->assertSame([
            'input' => 'My payouts have been failing for 3 days.',
            'questions' => [
                ['name' => 'is_bug', 'type' => 'predicate', 'instructions' => 'Is this a software defect?'],
                ['name' => 'department', 'type' => 'choice', 'instructions' => 'Which team should handle this?', 'choices' => [
                    ['value' => 'billing', 'description' => 'Payments'],
                    ['value' => 'technical'],
                ]],
                ['name' => 'frustration', 'type' => 'score', 'instructions' => 'How frustrated is the customer?', 'levels' => [
                    ['label' => 'Calm'],
                    ['label' => 'Very angry'],
                ]],
            ],
        ], $payload);
    }

    public function testItMovesNoulCriteriaIntoThePredicateInstructions()
    {
        $payload = $this->normalize(new Evaluation('state', [
            'is_urgent' => new NoulQuestion('Does this convey urgency?', 'Explicitly time-sensitive', 'No urgency expressed'),
        ]));

        $this->assertSame("Does this convey urgency?\nTrue means: Explicitly time-sensitive\nFalse means: No urgency expressed", $payload['questions'][0]['instructions']);
    }

    public function testItSendsNumericOptionsAsStrings()
    {
        $payload = $this->normalize(new Evaluation('state', [new ChoiceQuestion('Which line answers the query?', ['The first line', null])]));

        $this->assertSame('0', $payload['questions'][0]['name']);
        $this->assertSame([['value' => '0', 'description' => 'The first line'], ['value' => '1']], $payload['questions'][0]['choices']);
    }

    public function testItSendsStructuredStateAsJsonContent()
    {
        $payload = $this->normalize(new Evaluation(['amount' => 5000, 'country' => 'DE'], ['is_risky' => new NoulQuestion('Is this risky?')]));

        $this->assertSame([['role' => 'user', 'content' => [['type' => 'input_json', 'data' => ['amount' => 5000, 'country' => 'DE']]]]], $payload['input']);
    }

    public function testItSendsImagesAsDataUrls()
    {
        $payload = $this->normalize(new Evaluation('A yearbook page.', ['photo' => new NoulQuestion('Is this a photograph?')], [
            new Image('first image', 'image/png'),
        ]));

        $this->assertSame([['role' => 'user', 'content' => [
            ['type' => 'input_text', 'text' => 'A yearbook page.'],
            ['type' => 'input_image', 'image_url' => 'data:image/png;base64,'.base64_encode('first image')],
        ]]], $payload['input']);
    }

    public function testItRejectsImagesForModelsWithoutImageInput()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "text-only" does not support image inputs.');

        $this->normalize(new Evaluation('A photo', ['q' => new NoulQuestion('Portrait?')], [new Image('image', 'image/png')]), new Decision('text-only'));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(Evaluation $evaluation, Decision $model = new Decision('gpt-6-luna', [Capability::INPUT_TEXT, Capability::INPUT_IMAGE])): array
    {
        return (new EvaluationNormalizer())->normalize($evaluation, context: [Contract::CONTEXT_MODEL => $model]);
    }
}
