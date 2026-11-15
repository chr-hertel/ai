<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Bridge\Gemini\Decision;
use Symfony\AI\Platform\Bridge\Gemini\Decision\EvaluationNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
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

        $this->assertTrue($normalizer->supportsNormalization($evaluation, context: [Contract::CONTEXT_MODEL => new Decision('gemini-decision-1')]));
        $this->assertFalse($normalizer->supportsNormalization($evaluation, context: [Contract::CONTEXT_MODEL => new Gemini('gemini-2.5-flash')]));
    }

    public function testItNormalizesStateAndQuestions()
    {
        $payload = $this->normalize(new Evaluation('My payouts have been failing for 3 days.', [
            'is_urgent' => new NoulQuestion('Does this convey urgency?', 'Explicitly time-sensitive', 'No urgency expressed'),
            'is_bug' => new NoulQuestion('Is this a software defect?'),
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payments', 'technical' => null]),
            'frustration' => new ScoreQuestion('How frustrated is the customer?', ['Calm', 'Very angry']),
        ]));

        $this->assertJsonStringEqualsJsonString(<<<'JSON'
            {
                "input": "My payouts have been failing for 3 days.",
                "questions": {
                    "is_urgent": {"type": "noul", "instructions": "Does this convey urgency?", "criteria": {"true_description": "Explicitly time-sensitive", "false_description": "No urgency expressed"}},
                    "is_bug": {"type": "noul", "instructions": "Is this a software defect?"},
                    "department": {"type": "choice", "instructions": "Which team should handle this?", "criteria": {"billing": "Payments", "technical": ""}},
                    "frustration": {"type": "score", "instructions": "How frustrated is the customer?", "criteria": ["Calm", "Very angry"]}
                }
            }
            JSON, json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    public function testItEncodesStructuredStateAsJsonText()
    {
        $payload = $this->normalize(new Evaluation(['amount' => 5000, 'country' => 'DE'], ['is_risky' => new NoulQuestion('Is this risky?')]));

        $this->assertSame('{"amount":5000,"country":"DE"}', $payload['input']);
    }

    public function testItSendsImagesAsContentBlocks()
    {
        $payload = $this->normalize(new Evaluation('A yearbook page.', ['photo' => new NoulQuestion('Is this a photograph?')], [
            new Image('first image', 'image/png'),
            new Image('second image', 'image/jpeg'),
        ]));

        $this->assertSame([
            ['type' => 'text', 'text' => 'A yearbook page.'],
            ['type' => 'image', 'data' => base64_encode('first image'), 'mime_type' => 'image/png'],
            ['type' => 'image', 'data' => base64_encode('second image'), 'mime_type' => 'image/jpeg'],
        ], $payload['input']);
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
    private function normalize(Evaluation $evaluation, Decision $model = new Decision('gemini-decision-1', [Capability::INPUT_TEXT, Capability::INPUT_IMAGE])): array
    {
        return (new EvaluationNormalizer())->normalize($evaluation, context: [Contract::CONTEXT_MODEL => $model]);
    }
}
