<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Answer\Answers;
use Symfony\AI\Platform\Bridge\Decision\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\TypeSafe\Jev;
use Symfony\AI\Platform\Bridge\TypeSafe\SystemOneClient;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SystemOneClientTest extends TestCase
{
    public function testItSupportsJevModel()
    {
        $client = new SystemOneClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Jev('jev-latest')));
    }

    public function testItDoesNotSupportOtherModels()
    {
        $client = new SystemOneClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.typesafe.ai/v1/systemone', $url);
            $this->assertContains('Authorization: Bearer test-key', $options['headers']);
            $this->assertContains('Content-Type: application/json', $options['headers']);

            $this->assertJsonStringEqualsJsonString(<<<'JSON'
                {
                    "model": "jev-latest",
                    "state": "My payouts have been failing for 3 days.",
                    "questions": {
                        "is_urgent": {"type": "noul", "instructions": "Does this convey urgency?"},
                        "frustration": {"type": "score", "instructions": "How frustrated is the customer?", "criteria": ["Calm", "Very angry"]}
                    }
                }
                JSON, $options['body']);

            return new MockResponse();
        }]);

        $client = new SystemOneClient($httpClient, 'test-key');

        $client->request(new Jev('jev-latest'), [
            'state' => 'My payouts have been failing for 3 days.',
            'questions' => [
                'is_urgent' => ['type' => 'noul', 'instructions' => 'Does this convey urgency?'],
                'frustration' => ['type' => 'score', 'instructions' => 'How frustrated is the customer?', 'criteria' => ['Calm', 'Very angry']],
            ],
        ]);
    }

    public function testItKeepsNumericKeysAsJsonObjects()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertJsonStringEqualsJsonString(<<<'JSON'
                {
                    "model": "jev-latest",
                    "state": ["first line", "second line"],
                    "questions": {
                        "0": {"type": "choice", "instructions": "Which line answers the query?", "criteria": {"0": "The first line", "1": null}}
                    }
                }
                JSON, $options['body']);

            return new MockResponse();
        }]);

        $client = new SystemOneClient($httpClient, 'test-key');

        $client->request(new Jev('jev-latest'), [
            'state' => ['first line', 'second line'],
            'questions' => [
                ['type' => 'choice', 'instructions' => 'Which line answers the query?', 'criteria' => ['The first line', null]],
            ],
        ]);
    }

    public function testItUsesCustomBaseUrl()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url): MockResponse {
            $this->assertSame('https://typesafe.example.com/v1/systemone', $url);

            return new MockResponse();
        }]);

        $client = new SystemOneClient($httpClient, 'test-key', 'https://typesafe.example.com/');

        $client->request(new Jev('jev-latest'), [
            'state' => 'state',
            'questions' => ['is_urgent' => ['type' => 'noul', 'instructions' => 'Does this convey urgency?']],
        ]);
    }

    public function testItThrowsExceptionForStringPayload()
    {
        $client = new SystemOneClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decision payload must be an array with a "state" key and a "questions" map.');

        $client->request(new Jev('jev-latest'), 'invalid string payload');
    }

    public function testItThrowsExceptionForMissingStateKey()
    {
        $client = new SystemOneClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decision payload must be an array with a "state" key and a "questions" map.');

        $client->request(new Jev('jev-latest'), ['questions' => ['is_urgent' => ['type' => 'noul', 'instructions' => 'Urgent?']]]);
    }

    public function testItThrowsExceptionForMissingQuestionsKey()
    {
        $client = new SystemOneClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decision payload must be an array with a "state" key and a "questions" map.');

        $client->request(new Jev('jev-latest'), ['state' => 'state']);
    }

    public function testItThrowsExceptionForInvalidQuestion()
    {
        $client = new SystemOneClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question "is_urgent" must be an array, "string" given.');

        $client->request(new Jev('jev-latest'), ['state' => 'state', 'questions' => ['is_urgent' => 'Urgent?']]);
    }

    public function testItRejectsImagesBeforeSendingARequest()
    {
        $httpClient = new MockHttpClient();
        $client = new SystemOneClient($httpClient, 'test-key');

        try {
            $client->request(new Jev('jev-latest'), [
                'state' => 'A photo',
                'questions' => ['q' => ['type' => 'noul', 'instructions' => 'Portrait?']],
                'images' => [base64_encode('image')],
            ]);
            $this->fail('TypeSafe must reject image inputs.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The decision model "jev-latest" does not support image inputs.', $e->getMessage());
        }

        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testItConvertsAnswers()
    {
        $result = $this->createClient()->convert($this->createRawResult([
            'model' => 'jev-1.13.0',
            'answers' => [
                'is_urgent' => ['type' => 'noul', 'noul' => 0.92],
                'department' => ['type' => 'choice', 'choice' => 'technical', 'probabilities' => ['billing' => 0.08, 'technical' => 0.85, 'sales' => 0.07], 'confidence' => 0.82],
                'frustration' => ['type' => 'score', 'score' => 1.6, 'legend' => ['0' => 'Calm', '1' => 'Frustrated', '2' => 'Very angry'], 'probabilities' => ['0' => 0.05, '1' => 0.3, '2' => 0.65], 'confidence' => 0.78],
            ],
            'usage' => ['input_tokens' => 312, 'output_tokens' => 48],
        ]));

        $answers = $result->getContent();
        $this->assertInstanceOf(Answers::class, $answers);

        $this->assertSame(0.92, $answers->getNoul('is_urgent')->getProbability());

        $this->assertSame('technical', $answers->getChoice('department')->getChoice());
        $this->assertSame(['billing' => 0.08, 'technical' => 0.85, 'sales' => 0.07], $answers->getChoice('department')->getProbabilities());
        $this->assertSame(0.82, $answers->getChoice('department')->getConfidence());

        $this->assertSame(1.6, $answers->getScore('frustration')->getScore());
        $this->assertSame(['Calm', 'Frustrated', 'Very angry'], $answers->getScore('frustration')->getLegend());
        $this->assertSame([0.05, 0.3, 0.65], $answers->getScore('frustration')->getProbabilities());
        $this->assertSame(0.78, $answers->getScore('frustration')->getConfidence());
    }

    public function testItConvertsIntegerNumbersToFloats()
    {
        $result = $this->createClient()->convert($this->createRawResult([
            'answers' => [
                'department' => ['type' => 'choice', 'choice' => 'billing', 'probabilities' => ['billing' => 1, 'sales' => 0], 'confidence' => 1],
            ],
        ]));

        $answers = $result->getContent();
        $this->assertInstanceOf(Answers::class, $answers);
        $this->assertSame(['billing' => 1.0, 'sales' => 0.0], $answers->getChoice('department')->getProbabilities());
        $this->assertSame(1.0, $answers->getChoice('department')->getConfidence());
    }

    public function testItThrowsExceptionForScoreWithoutProbabilities()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "frustration" of type "score" is missing the "probabilities" key.');

        $this->createClient()->convert($this->createRawResult([
            'answers' => [
                'frustration' => ['type' => 'score', 'score' => 1.035, 'legend' => ['0' => 'Calm', '1' => 'Frustrated'], 'confidence' => 0.842],
            ],
        ]));
    }

    public function testItThrowsExceptionForMissingAnswers()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain answers.');

        $this->createClient()->convert($this->createRawResult(['model' => 'jev-1.13.0']));
    }

    public function testItThrowsExceptionForUnsupportedAnswerType()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "is_urgent" has an unsupported type "boolean".');

        $this->createClient()->convert($this->createRawResult(['answers' => ['is_urgent' => ['type' => 'boolean']]]));
    }

    public function testItThrowsAuthenticationExceptionOnUnauthorized()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Cannot authenticate with the server. Please check your API key and try again.');

        $this->createClient()->convert($this->createRawResult([
            'detail' => ['error_type' => 'authentication_error', 'message' => 'Cannot authenticate with the server. Please check your API key and try again.'],
        ], 401));
    }

    public function testItThrowsBadRequestExceptionOnValidationError()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('body.questions: Field required; body.state: Field required');

        $this->createClient()->convert($this->createRawResult(['detail' => [
            ['type' => 'missing', 'loc' => ['body', 'questions'], 'msg' => 'Field required', 'input' => []],
            ['type' => 'missing', 'loc' => ['body', 'state'], 'msg' => 'Field required', 'input' => []],
        ]], 422));
    }

    public function testItThrowsBadRequestExceptionOnUnknownModel()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unknown model: jev');

        $this->createClient()->convert($this->createRawResult(['detail' => ['error_type' => 'api_usage_error', 'message' => 'Unknown model: jev']], 400));
    }

    public function testItThrowsBadRequestExceptionWithPlainDetail()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Choice question must have at least one choice: department');

        $this->createClient()->convert($this->createRawResult(['detail' => 'Choice question must have at least one choice: department'], 400));
    }

    public function testItThrowsExceptionForMissingAnswerKey()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "department" of type "choice" is missing the "confidence" key.');

        $this->createClient()->convert($this->createRawResult(['answers' => [
            'department' => ['type' => 'choice', 'choice' => 'billing', 'probabilities' => ['billing' => 1.0], 'confidence' => null],
        ]]));
    }

    public function testItThrowsExceptionForNonArrayAnswer()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "is_urgent" is expected to be an array, "string" given.');

        $this->createClient()->convert($this->createRawResult(['answers' => ['is_urgent' => 'invalid']]));
    }

    public function testItThrowsExceptionForNonArrayProbabilities()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "department" of type "choice" expects the "probabilities" key to be an array, "string" given.');

        $this->createClient()->convert($this->createRawResult(['answers' => [
            'department' => ['type' => 'choice', 'choice' => 'billing', 'probabilities' => 'invalid', 'confidence' => 1.0],
        ]]));
    }

    public function testItThrowsExceptionForNonArrayLegend()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "frustration" of type "score" expects the "legend" key to be an array, "string" given.');

        $this->createClient()->convert($this->createRawResult(['answers' => [
            'frustration' => ['type' => 'score', 'score' => 1.6, 'legend' => 'invalid', 'probabilities' => ['0' => 1.0], 'confidence' => 0.78],
        ]]));
    }

    public function testItThrowsExceptionForNonStringChoice()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "department" of type "choice" expects the "choice" key to be a string, "array" given.');

        $this->createClient()->convert($this->createRawResult(['answers' => [
            'department' => ['type' => 'choice', 'choice' => ['billing'], 'probabilities' => ['billing' => 1.0], 'confidence' => 1.0],
        ]]));
    }

    public function testItIgnoresValidationErrorsWithMalformedLocation()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unprocessable Entity');

        $this->createClient()->convert($this->createRawResult(['detail' => [
            ['type' => 'missing', 'loc' => 'body', 'msg' => 'Field required'],
        ]], 422));
    }

    public function testItThrowsRateLimitExceededExceptionOnTooManyRequests()
    {
        $this->expectException(RateLimitExceededException::class);

        $this->createClient()->convert($this->createRawResult(['detail' => ['error_type' => 'rate_limit_error', 'message' => 'Rate limit exceeded']], 429));
    }

    public function testItThrowsServerExceptionWhenOverloaded()
    {
        $this->expectException(ServerException::class);

        $this->createClient()->convert($this->createRawResult(['detail' => ['error_type' => 'overloaded_error', 'message' => 'Overloaded']], 529));
    }

    public function testItReturnsTokenUsageExtractor()
    {
        $this->assertInstanceOf(TokenUsageExtractor::class, $this->createClient()->getTokenUsageExtractor());
    }

    private function createClient(): SystemOneClient
    {
        return new SystemOneClient(new MockHttpClient(), 'test-key');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRawResult(array $data, int $statusCode = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data, ['http_code' => $statusCode]));

        return new RawHttpResult($httpClient->request('POST', 'https://api.typesafe.ai/v1/systemone'));
    }
}
