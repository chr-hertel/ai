<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\TranscriptionClient;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\HttpTransport;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Result\Segment;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Result\Transcript;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Task;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TranscriptionClientTest extends TestCase
{
    private TranscriptionClient $resultConverter;

    protected function setUp(): void
    {
        $this->resultConverter = self::client();
    }

    public function testSupportsWhisperModel()
    {
        $this->assertTrue($this->resultConverter->supports(new Whisper('whisper-1')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $this->assertFalse($this->resultConverter->supports(new Model('generic-model')));
    }

    public function testConvertNonVerboseResult()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello, this is a transcription.',
        ]);

        $result = $this->resultConverter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, this is a transcription.', $result->getContent());
    }

    public function testConvertNonVerboseResultWithVerboseOptionFalse()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello, this is a transcription.',
        ]);

        $result = $this->resultConverter->convert($rawResult, ['verbose' => false]);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, this is a transcription.', $result->getContent());
    }

    public function testConvertNonVerboseResultWithUsage()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello, this is a transcription.',
            'usage' => ['type' => 'duration', 'duration' => 3],
        ]);

        $result = $this->resultConverter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame(['type' => 'duration', 'duration' => 3], $result->getMetadata()->get('usage'));
    }

    public function testConvertVerboseResult()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello, world!',
            'language' => 'en',
            'duration' => 5.5,
            'segments' => [
                ['start' => 0.0, 'end' => 2.5, 'text' => 'Hello,'],
                ['start' => 2.5, 'end' => 5.5, 'text' => ' world!'],
            ],
        ]);

        $result = $this->resultConverter->convert($rawResult, ['verbose' => true]);

        $this->assertInstanceOf(ObjectResult::class, $result);

        $transcript = $result->getContent();
        $this->assertInstanceOf(Transcript::class, $transcript);
        $this->assertSame('Hello, world!', $transcript->getText());
        $this->assertSame('en', $transcript->getLanguage());
        $this->assertSame(5.5, $transcript->getDuration());

        $segments = $transcript->getSegments();
        $this->assertCount(2, $segments);

        $this->assertInstanceOf(Segment::class, $segments[0]);
        $this->assertSame(0.0, $segments[0]->getStart());
        $this->assertSame(2.5, $segments[0]->getEnd());
        $this->assertSame('Hello,', $segments[0]->getText());

        $this->assertInstanceOf(Segment::class, $segments[1]);
        $this->assertSame(2.5, $segments[1]->getStart());
        $this->assertSame(5.5, $segments[1]->getEnd());
        $this->assertSame(' world!', $segments[1]->getText());
    }

    public function testConvertVerboseResultWithUsage()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello',
            'language' => 'en',
            'duration' => 1.0,
            'segments' => [],
            'usage' => ['type' => 'duration', 'duration' => 3],
        ]);

        $result = $this->resultConverter->convert($rawResult, ['verbose' => true]);

        $this->assertInstanceOf(ObjectResult::class, $result);
        $this->assertSame(['type' => 'duration', 'duration' => 3], $result->getMetadata()->get('usage'));
    }

    public function testVerboseResultThrowsExceptionWhenMissingText()
    {
        $rawResult = $this->createRawResult([
            'language' => 'en',
            'duration' => 5.5,
            'segments' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The verbose response is missing required fields: text, language, duration, or segments.');

        $this->resultConverter->convert($rawResult, ['verbose' => true]);
    }

    public function testVerboseResultThrowsExceptionWhenMissingLanguage()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello',
            'duration' => 5.5,
            'segments' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The verbose response is missing required fields: text, language, duration, or segments.');

        $this->resultConverter->convert($rawResult, ['verbose' => true]);
    }

    public function testVerboseResultThrowsExceptionWhenMissingDuration()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello',
            'language' => 'en',
            'segments' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The verbose response is missing required fields: text, language, duration, or segments.');

        $this->resultConverter->convert($rawResult, ['verbose' => true]);
    }

    public function testVerboseResultThrowsExceptionWhenMissingSegments()
    {
        $rawResult = $this->createRawResult([
            'text' => 'Hello',
            'language' => 'en',
            'duration' => 5.5,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The verbose response is missing required fields: text, language, duration, or segments.');

        $this->resultConverter->convert($rawResult, ['verbose' => true]);
    }

    public function testGetTokenUsageExtractorReturnsNull()
    {
        $this->assertNull($this->resultConverter->getTokenUsageExtractor());
    }

    public function testThrowsAuthenticationExceptionOn401()
    {
        $httpResponse = new MockResponse(json_encode([
            'error' => [
                'message' => 'Invalid API key provided: sk-invalid',
            ],
        ]), ['http_code' => 401]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key provided: sk-invalid');

        self::requestWith($httpResponse);
    }

    public function testThrowsBadRequestExceptionOn400()
    {
        $httpResponse = new MockResponse(json_encode([
            'error' => [
                'message' => 'Invalid file format.',
            ],
        ]), ['http_code' => 400]);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid file format.');

        self::requestWith($httpResponse);
    }

    public function testThrowsBadRequestExceptionOn400WithNoMessage()
    {
        $httpResponse = new MockResponse('{}', ['http_code' => 400]);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request');

        self::requestWith($httpResponse);
    }

    public function testThrowsRateLimitExceededExceptionOn429()
    {
        $httpResponse = new MockResponse('{"error":{"message":"You exceeded your current quota, please check your plan and billing details."}}', ['http_code' => 429]);

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded. You exceeded your current quota, please check your plan and billing details.');

        self::requestWith($httpResponse);
    }

    public function testThrowsContentFilterException()
    {
        $rawResult = $this->createRawResult([
            'error' => [
                'code' => 'content_filter',
                'message' => 'Content was filtered due to policy violation.',
            ],
        ]);

        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage('Content was filtered due to policy violation.');

        $this->resultConverter->convert($rawResult);
    }

    public function testThrowsRuntimeExceptionOnGenericError()
    {
        $rawResult = $this->createRawResult([
            'error' => [
                'code' => 'server_error',
                'type' => 'internal',
                'param' => null,
                'message' => 'Something went wrong',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "server_error"-internal (-): "Something went wrong".');

        $this->resultConverter->convert($rawResult);
    }

    public function testThrowsRuntimeExceptionWhenTextFieldMissing()
    {
        $rawResult = $this->createRawResult([
            'task' => 'transcribe',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The response is missing the required "text" field.');

        $this->resultConverter->convert($rawResult);
    }

    public function testItUsesTranscriptionEndpointByDefault()
    {
        $httpClient = self::expectUrl('https://api.openai.com/v1/audio/transcriptions');

        self::client($httpClient)->request(new Whisper('whisper-1'), ['file' => 'audio-data']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUsesTranscriptionEndpointWhenTaskIsSpecified()
    {
        $httpClient = self::expectUrl('https://api.openai.com/v1/audio/transcriptions');

        self::client($httpClient)->request(new Whisper('whisper-1'), ['file' => 'audio-data'], ['task' => Task::TRANSCRIPTION]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUsesTranslationEndpointWhenTaskIsSpecified()
    {
        $httpClient = self::expectUrl('https://api.openai.com/v1/audio/translations');

        self::client($httpClient)->request(new Whisper('whisper-1'), ['file' => 'audio-data'], ['task' => Task::TRANSLATION]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    #[TestWith(['EU', 'https://eu.api.openai.com/v1/audio/transcriptions'])]
    #[TestWith(['US', 'https://us.api.openai.com/v1/audio/transcriptions'])]
    #[TestWith([null, 'https://api.openai.com/v1/audio/transcriptions'])]
    public function testItUsesCorrectRegionUrlForTranscription(?string $region, string $expectedUrl)
    {
        $httpClient = self::expectUrl($expectedUrl);

        self::client($httpClient, $region)->request(new Whisper('whisper-1'), ['file' => 'audio-data']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    #[TestWith(['EU', 'https://eu.api.openai.com/v1/audio/translations'])]
    #[TestWith(['US', 'https://us.api.openai.com/v1/audio/translations'])]
    #[TestWith([null, 'https://api.openai.com/v1/audio/translations'])]
    public function testItUsesCorrectRegionUrlForTranslation(?string $region, string $expectedUrl)
    {
        $httpClient = self::expectUrl($expectedUrl);

        self::client($httpClient, $region)->request(new Whisper('whisper-1'), ['file' => 'audio-data'], ['task' => Task::TRANSLATION]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItSkipsResponseFormatUnlessVerbose()
    {
        $httpClient = new MockHttpClient([
            function (string $method, string $url, array $options): MockResponse {
                $body = self::bodyOf($options);
                $this->assertStringNotContainsString('response_format', $body);
                $this->assertStringNotContainsString('verbose_json', $body);

                return new JsonMockResponse(['text' => 'Hello World']);
            },
        ]);

        self::client($httpClient)->request(new Whisper('whisper-1'), ['file' => 'audio-data']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUsesResponseFormatIfVerbose()
    {
        $httpClient = new MockHttpClient([
            function (string $method, string $url, array $options): MockResponse {
                $body = self::bodyOf($options);
                $this->assertStringContainsString('response_format', $body);
                $this->assertStringContainsString('verbose_json', $body);
                $this->assertStringNotContainsString('verbose=', $body);

                return new JsonMockResponse(['text' => 'Hello World']);
            },
        ]);

        self::client($httpClient)->request(new Whisper('whisper-1'), ['file' => 'audio-data'], ['verbose' => true]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRawResult(array $data, int $statusCode = 200): RawHttpResult
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn($statusCode);
        $httpResponse->method('toArray')->willReturn($data);

        return new RawHttpResult($httpResponse);
    }

    private static function client(MockHttpClient $httpClient = new MockHttpClient(), ?string $region = null): TranscriptionClient
    {
        return new TranscriptionClient(new HttpTransport($httpClient, 'sk-test-key', $region));
    }

    private static function expectUrl(string $expectedUrl): MockHttpClient
    {
        return new MockHttpClient([
            static function (string $method, string $url) use ($expectedUrl): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame($expectedUrl, $url);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);
    }

    private static function requestWith(MockResponse $response): void
    {
        $client = self::client(new MockHttpClient($response));
        $client->convert($client->request(new Whisper('whisper-1'), ['file' => 'audio-data']));
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function bodyOf(array $options): string
    {
        $body = $options['body'];

        if (\is_string($body)) {
            return $body;
        }

        $content = '';
        if ($body instanceof \Closure) {
            while ('' !== ($chunk = $body(8192))) {
                $content .= $chunk;
            }

            return $content;
        }

        foreach ($body as $chunk) {
            $content .= $chunk;
        }

        return $content;
    }
}
