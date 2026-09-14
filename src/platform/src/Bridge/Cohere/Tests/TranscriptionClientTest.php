<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\Cohere;
use Symfony\AI\Platform\Bridge\Cohere\SpeechToText;
use Symfony\AI\Platform\Bridge\Cohere\TranscriptionClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TranscriptionClientTest extends TestCase
{
    public function testItSupportsSpeechToTextModel()
    {
        $client = new TranscriptionClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new SpeechToText('cohere-transcribe-03-2026')));
    }

    public function testItDoesNotSupportCohereModel()
    {
        $client = new TranscriptionClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Cohere('command-a-03-2025')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.cohere.com/v2/audio/transcriptions', $url);
            $this->assertStringContainsString('Bearer test-key', $options['normalized_headers']['authorization'][0]);
            $this->assertStringContainsString('multipart/form-data', $options['normalized_headers']['content-type'][0]);

            return new MockResponse('{"text": "Hello world"}');
        }]);

        $client = new TranscriptionClient($httpClient, 'test-key');

        $client->request(new SpeechToText('cohere-transcribe-03-2026'), ['file' => 'audio-data', 'language' => 'en']);
    }

    public function testStringPayloadThrowsException()
    {
        $client = new TranscriptionClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given');

        $client->request(new SpeechToText('cohere-transcribe-03-2026'), 'string payload');
    }

    public function testItThrowsExceptionOnNon200StatusCode()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        self::request(new MockResponse('Internal Server Error', ['http_code' => 500]));
    }

    public function testItThrowsModelNotFoundExceptionOnNotFound()
    {
        $this->expectException(ModelNotFoundException::class);

        self::request(new JsonMockResponse(['message' => "model 'transcribe-z' not found"], ['http_code' => 404]));
    }

    public function testThrowsRateLimitExceededExceptionWithRetryAfterHeader()
    {
        try {
            self::request(new JsonMockResponse(['message' => 'trial key rate limit exceeded'], ['http_code' => 429, 'response_headers' => ['retry-after' => '30']]));
            $this->fail('Expected a RateLimitExceededException to be thrown.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame(30, $e->getRetryAfter());
        }
    }

    public function testItConvertsResponseToTextResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'text' => 'Hello, this is a transcription test.',
        ]);

        $converter = new TranscriptionClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, this is a transcription test.', $result->getContent());
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainText()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['invalid' => 'response']);

        $converter = new TranscriptionClient(new MockHttpClient(), 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain transcription text.');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItReturnsNullTokenUsageExtractor()
    {
        $converter = new TranscriptionClient(new MockHttpClient(), 'test-key');

        $this->assertNull($converter->getTokenUsageExtractor());
    }

    private static function request(MockResponse $response): void
    {
        $client = new TranscriptionClient(new MockHttpClient($response), 'test-key');
        $client->convert($client->request(new SpeechToText('cohere-transcribe-03-2026'), ['file' => 'audio-data']));
    }
}
