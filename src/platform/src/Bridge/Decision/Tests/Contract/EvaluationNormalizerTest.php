<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decision\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Contract\EvaluationNormalizer;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Model;

final class EvaluationNormalizerTest extends TestCase
{
    public function testItSupportsEvaluationsForAnyModel()
    {
        $normalizer = new EvaluationNormalizer();
        $evaluation = new Evaluation('state', ['is_urgent' => new NoulQuestion('Urgent?')]);

        $this->assertTrue($normalizer->supportsNormalization($evaluation, context: [Contract::CONTEXT_MODEL => new Model('jev-latest')]));
        $this->assertFalse($normalizer->supportsNormalization('state', context: [Contract::CONTEXT_MODEL => new Model('jev-latest')]));
    }

    public function testItNormalizesStateAndQuestions()
    {
        $payload = $this->normalize(new Evaluation('My payouts have been failing for 3 days.', [
            'is_urgent' => new NoulQuestion('Does this convey urgency?'),
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payments', 'technical' => null]),
            'frustration' => new ScoreQuestion('How frustrated is the customer?', ['Calm', 'Very angry']),
        ]));

        $this->assertJsonStringEqualsJsonString(<<<'JSON'
            {
                "state": "My payouts have been failing for 3 days.",
                "questions": {
                    "is_urgent": {"type": "noul", "instructions": "Does this convey urgency?"},
                    "department": {"type": "choice", "instructions": "Which team should handle this?", "criteria": {"billing": "Payments", "technical": null}},
                    "frustration": {"type": "score", "instructions": "How frustrated is the customer?", "criteria": ["Calm", "Very angry"]}
                }
            }
            JSON, json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    public function testItNormalizesTheGivenNoulCriteria()
    {
        $payload = $this->normalize(new Evaluation('state', [
            'both' => new NoulQuestion('Urgent?', 'Explicitly time-sensitive', 'No urgency expressed'),
            'false_only' => new NoulQuestion('Urgent?', false: 'No urgency expressed'),
        ]));

        $this->assertJsonStringEqualsJsonString(<<<'JSON'
            {
                "both": {"type": "noul", "instructions": "Urgent?", "criteria": {"true": "Explicitly time-sensitive", "false": "No urgency expressed"}},
                "false_only": {"type": "noul", "instructions": "Urgent?", "criteria": {"false": "No urgency expressed"}}
            }
            JSON, json_encode($payload['questions'], \JSON_THROW_ON_ERROR));
    }

    public function testItKeepsNumericKeysAsJsonObjects()
    {
        $payload = $this->normalize(new Evaluation(['first line', 'second line'], [
            new ChoiceQuestion('Which line answers the query?', ['The first line', null]),
        ]));

        $this->assertJsonStringEqualsJsonString(<<<'JSON'
            {
                "state": ["first line", "second line"],
                "questions": {
                    "0": {"type": "choice", "instructions": "Which line answers the query?", "criteria": {"0": "The first line", "1": null}}
                }
            }
            JSON, json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    public function testItNormalizesImagesInOrderAsRawBase64()
    {
        $payload = $this->normalize(new Evaluation('A yearbook page.', ['photo' => new NoulQuestion('Is this a photograph?')], [
            new Image('first image', 'image/png'),
            new Image('second image', 'image/jpeg'),
        ]), new Model('clef', [Capability::INPUT_IMAGE]));

        $this->assertSame([base64_encode('first image'), base64_encode('second image')], $payload['images']);
    }

    public function testItOmitsEmptyImages()
    {
        $this->assertArrayNotHasKey('images', $this->normalize(new Evaluation('text', ['q' => new NoulQuestion('Relevant?')])));
    }

    public function testItRejectsImagesForModelsWithoutImageInput()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "jev-latest" does not support image inputs.');

        $this->normalize(new Evaluation('A photo', ['q' => new NoulQuestion('Portrait?')], [new Image('image', 'image/png')]));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(Evaluation $evaluation, Model $model = new Model('jev-latest')): array
    {
        return (new EvaluationNormalizer())->normalize($evaluation, context: [Contract::CONTEXT_MODEL => $model]);
    }
}
