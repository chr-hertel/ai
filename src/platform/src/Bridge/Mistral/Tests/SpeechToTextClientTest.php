<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Bridge\Mistral\SpeechToText;
use Symfony\AI\Platform\Bridge\Mistral\SpeechToTextClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SpeechToTextClientTest extends TestCase
{
    public function testItSupportsSpeechToTextModel()
    {
        $client = new SpeechToTextClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new SpeechToText('voxtral-mini-latest')));
    }

    public function testItDoesNotSupportMistralModel()
    {
        $client = new SpeechToTextClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Mistral('mistral-large-latest')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mistral.ai/v1/audio/transcriptions', $url);
            $this->assertStringContainsString('Bearer test-key', $options['normalized_headers']['authorization'][0]);
            $this->assertStringContainsString('multipart/form-data', $options['normalized_headers']['content-type'][0]);

            return new MockResponse('{"text": "Hello world"}');
        }]);

        $client = new SpeechToTextClient($httpClient, 'test-key');

        $client->request(new SpeechToText('voxtral-mini-latest'), ['file' => 'audio-data', 'language' => 'en']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItHonorsCustomBaseUrl()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
        ): MockResponse {
            $this->assertSame('https://example.com/v1/audio/transcriptions', $url);

            return new MockResponse('{"text": "Hello world"}');
        }]);

        $client = new SpeechToTextClient($httpClient, 'test-key', 'https://example.com/');

        $client->request(new SpeechToText('voxtral-mini-latest'), ['file' => 'audio-data']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStringPayloadThrowsException()
    {
        $client = new SpeechToTextClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given');

        $client->request(new SpeechToText('voxtral-mini-latest'), 'string payload');
    }

    public function testItThrowsExceptionOnServerError()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getContent')->willReturn('Internal Server Error');

        $converter = new SpeechToTextClient(new MockHttpClient(), 'test-key');

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItConvertsResponseToTextResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'text' => 'Hello, this is a transcription test.',
        ]);

        $converter = new SpeechToTextClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, this is a transcription test.', $result->getContent());
    }

    public function testItAddsUsageMetadataWhenPresent()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'text' => 'Hello world.',
            'usage' => ['prompt_audio_seconds' => 12],
        ]);

        $converter = new SpeechToTextClient(new MockHttpClient(), 'test-key');
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertSame(['prompt_audio_seconds' => 12], $result->getMetadata()->get('usage'));
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainText()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['invalid' => 'response']);

        $converter = new SpeechToTextClient(new MockHttpClient(), 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain transcription text.');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItReturnsNullTokenUsageExtractor()
    {
        $converter = new SpeechToTextClient(new MockHttpClient(), 'test-key');

        $this->assertNull($converter->getTokenUsageExtractor());
    }
}
