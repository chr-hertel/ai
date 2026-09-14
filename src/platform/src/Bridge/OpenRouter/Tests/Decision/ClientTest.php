<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Answer\Answers;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Bridge\Decision\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\Decision\Client;
use Symfony\AI\Platform\Bridge\OpenRouter\DecisionModel;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ClientTest extends TestCase
{
    public function testItSupportsOnlyDecisionModels()
    {
        $client = new Client(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new DecisionModel('typesafe/jev-1.13')));
        $this->assertFalse($client->supports(new CompletionsModel('openrouter/auto')));
    }

    public function testItSendsTheEvaluationToTheDecisionsEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://openrouter.ai/api/alpha/decisions', $url);
            $this->assertContains('Authorization: Bearer test-key', $options['headers']);
            $this->assertSame('{"model":"typesafe\/jev-1.13","state":"My invoice is wrong.","questions":{"department":{"type":"choice","instructions":"Which team should handle this?","criteria":{"billing":"Payment issues","technical":null}},"frustration":{"type":"score","instructions":"How frustrated is the customer?","criteria":["Calm","Angry"]},"is_bug":{"type":"noul","instructions":"Is this a software defect?","criteria":{"true":"Broken behavior","false":"A question"}}}}', $options['body']);

            return new MockResponse();
        });

        $client = new Client($httpClient, 'test-key');
        $client->request(new DecisionModel('typesafe/jev-1.13'), (new Evaluation('My invoice is wrong.', [
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payment issues', 'technical' => null]),
            'frustration' => new ScoreQuestion('How frustrated is the customer?', ['Calm', 'Angry']),
            'is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior', 'A question'),
        ]))->jsonSerialize());
    }

    public function testItForwardsTheSupportedOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = json_decode($options['body'], true);

            $this->assertSame(['order' => ['typesafe'], 'allow_fallbacks' => false], $body['provider']);
            $this->assertSame('session-1', $body['session_id']);
            $this->assertSame(['trace_id' => 'trace-1'], $body['trace']);
            $this->assertSame('user-1', $body['user']);
            $this->assertArrayNotHasKey('temperature', $body);

            return new MockResponse();
        });

        $client = new Client($httpClient, 'test-key');
        $client->request(new DecisionModel('typesafe/jev-1.13'), $this->createPayload(), [
            'provider' => ['order' => ['typesafe'], 'allow_fallbacks' => false],
            'session_id' => 'session-1',
            'trace' => ['trace_id' => 'trace-1'],
            'user' => 'user-1',
            'temperature' => 0.5,
        ]);
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('https://openrouter.example.com/alpha/decisions', $url);

            return new MockResponse();
        });

        $client = new Client($httpClient, 'test-key', 'https://openrouter.example.com/');
        $client->request(new DecisionModel('typesafe/jev-1.13'), $this->createPayload());
    }

    public function testItAcceptsNoulQuestionsWithoutCriteria()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('{"model":"typesafe\/jev-1.13","state":"My invoice is wrong.","questions":{"is_bug":{"type":"noul","instructions":"Is this a software defect?"}}}', $options['body']);

            return new MockResponse();
        });

        $client = new Client($httpClient, 'test-key');
        $client->request(new DecisionModel('typesafe/jev-1.13'), (new Evaluation('My invoice is wrong.', [
            'is_bug' => new NoulQuestion('Is this a software defect?'),
        ]))->jsonSerialize());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRejectsNoulQuestionsWithPartialCriteria()
    {
        $client = new Client(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The noul question "is_bug" requires both a "true" and a "false" criterion, or none, for OpenRouter decision models.');

        $client->request(new DecisionModel('typesafe/jev-1.13'), (new Evaluation('My invoice is wrong.', [
            'is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior'),
        ]))->jsonSerialize());
    }

    public function testItRejectsImages()
    {
        $client = new Client(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "typesafe/jev-1.13" does not support image inputs.');

        $client->request(new DecisionModel('typesafe/jev-1.13'), (new Evaluation('Is this a cat?', [
            'is_cat' => new NoulQuestion('Does the image show a cat?', 'A cat', 'No cat'),
        ], [new Image('image data', 'image/png')]))->jsonSerialize());
    }

    public function testItRejectsInvalidPayloads()
    {
        $client = new Client(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decision payload must be an array with a "state" key and a "questions" map.');

        $client->request(new DecisionModel('typesafe/jev-1.13'), 'invalid string payload');
    }

    public function testItConvertsTheAnswers()
    {
        $result = (new Client(new MockHttpClient(), 'test-key'))->convert($this->createRawResult($this->createResponseData()));

        $answers = $result->getContent();
        $this->assertInstanceOf(Answers::class, $answers);
        $this->assertSame(0.96, $answers->getNoul('is_bug')->getProbability());
        $this->assertSame('technical', $answers->getChoice('department')->getChoice());
        $this->assertSame(0.88, $answers->getChoice('department')->getConfidence());
        $this->assertSame(['billing' => 0.08, 'technical' => 0.92], $answers->getChoice('department')->getProbabilities());
        $this->assertSame(1.4, $answers->getScore('frustration')->getScore());
        $this->assertSame([0 => 'Calm', 1 => 'Frustrated', 2 => 'Angry'], $answers->getScore('frustration')->getLegend());
    }

    public function testItExtractsTheTokenUsage()
    {
        $client = new Client(new MockHttpClient(), 'test-key');
        $extractor = $client->getTokenUsageExtractor();

        $this->assertInstanceOf(TokenUsageExtractor::class, $extractor);

        $tokenUsage = $extractor->extract($this->createRawResult($this->createResponseData()));
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(100, $tokenUsage->getPromptTokens());
        $this->assertSame(10, $tokenUsage->getCompletionTokens());
    }

    public function testItReportsBadRequestsWithTheOpenRouterErrorMessage()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('questions.is_bug.criteria must contain "true" and "false"');

        (new Client(new MockHttpClient(), 'test-key'))->convert($this->createRawResult(['error' => ['code' => 400, 'message' => 'questions.is_bug.criteria must contain "true" and "false"']], 400));
    }

    public function testItReportsAuthenticationFailures()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No auth credentials found');

        (new Client(new MockHttpClient(), 'test-key'))->convert($this->createRawResult(['error' => ['code' => 401, 'message' => 'No auth credentials found']], 401));
    }

    public function testItReportsRateLimits()
    {
        $this->expectException(RateLimitExceededException::class);

        (new Client(new MockHttpClient(), 'test-key'))->convert($this->createRawResult(['error' => ['code' => 429, 'message' => 'Rate limit exceeded']], 429));
    }

    public function testItReportsServerErrors()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Provider returned error');

        (new Client(new MockHttpClient(), 'test-key'))->convert($this->createRawResult(['error' => ['code' => 502, 'message' => 'Provider returned error']], 502));
    }

    public function testItReportsInsufficientCredits()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 402: "Insufficient credits"');

        (new Client(new MockHttpClient(), 'test-key'))->convert($this->createRawResult(['error' => ['code' => 402, 'message' => 'Insufficient credits']], 402));
    }

    /**
     * @return array<string, mixed>
     */
    private function createResponseData(): array
    {
        return [
            'id' => 'gen-dec-123',
            'model' => 'typesafe/jev-1.13-20260917',
            'provider' => 'TypeSafe',
            'answers' => [
                'is_bug' => ['type' => 'noul', 'noul' => 0.96],
                'department' => [
                    'type' => 'choice',
                    'choice' => 'technical',
                    'confidence' => 0.88,
                    'probabilities' => ['billing' => 0.08, 'technical' => 0.92],
                ],
                'frustration' => [
                    'type' => 'score',
                    'score' => 1.4,
                    'confidence' => 0.7,
                    'probabilities' => ['0' => 0.1, '1' => 0.4, '2' => 0.5],
                    'legend' => ['0' => 'Calm', '1' => 'Frustrated', '2' => 'Angry'],
                ],
            ],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 10, 'cost' => 0.00002],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRawResult(array $data, int $statusCode = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data, ['http_code' => $statusCode]));

        return new RawHttpResult($httpClient->request('POST', 'https://openrouter.ai/api/alpha/decisions'));
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(): array
    {
        return (new Evaluation('My invoice is wrong.', [
            'is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior', 'A question'),
        ]))->jsonSerialize();
    }
}
