<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Bridge\Ollama\TextToSpeech;
use Symfony\AI\Platform\Bridge\Ollama\TextToSpeech\Format;
use Symfony\AI\Platform\Bridge\Ollama\TextToSpeechClient;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

final class TextToSpeechClientTest extends TestCase
{
    public function testSupportsTextToSpeechModel()
    {
        $client = new TextToSpeechClient(new MockHttpClient());

        $this->assertTrue($client->supports(new TextToSpeech('phonon')));
        $this->assertFalse($client->supports(new Ollama('llama3.2')));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testItRequestsSpeechWithAStringPayload()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): HttpResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('http://127.0.0.1:11434/v1/audio/speech', $url);
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);

            $body = json_decode($options['body'], true);
            $this->assertSame('phonon', $body['model']);
            $this->assertSame('Hello from Ollama.', $body['input']);
            $this->assertSame('Zoey', $body['voice']);
            $this->assertSame('wav', $body['response_format']);
            $this->assertSame(1, $body['speed']);

            return new MockResponse('fake-audio-bytes');
        }, 'http://127.0.0.1:11434');

        (new TextToSpeechClient($httpClient))->request(new TextToSpeech('phonon'), 'Hello from Ollama.', [
            'voice' => 'Zoey',
            'response_format' => 'wav',
            'speed' => 1,
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRequestsSpeechWithAnArrayPayload()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): HttpResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame('phonon', $body['model']);
            $this->assertSame('Hello from Ollama.', $body['input']);
            $this->assertArrayNotHasKey('voice', $body);

            return new MockResponse('fake-audio-bytes');
        }, 'http://127.0.0.1:11434');

        (new TextToSpeechClient($httpClient))->request(new TextToSpeech('phonon'), ['type' => 'text', 'text' => 'Hello from Ollama.']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItFailsWithoutATextPayload()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must contain a "text" key.');

        (new TextToSpeechClient(new MockHttpClient()))->request(new TextToSpeech('phonon'), ['type' => 'text']);
    }

    public function testItFailsWithANullTextPayload()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload "text" key must be a string.');

        (new TextToSpeechClient(new MockHttpClient()))->request(new TextToSpeech('phonon'), ['text' => null]);
    }

    public function testItAcceptsDisabledStreaming()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): HttpResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame('Hello from Ollama.', $body['input']);
            $this->assertArrayNotHasKey('stream', $body);
            $this->assertArrayNotHasKey('stream_format', $body);

            return new MockResponse('fake-audio-bytes');
        }, 'http://127.0.0.1:11434');

        (new TextToSpeechClient($httpClient))->request(new TextToSpeech('phonon'), 'Hello from Ollama.', [
            'stream' => false,
            'stream_format' => null,
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItFailsWithANonStringTextPayload()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload "text" key must be a string.');

        (new TextToSpeechClient(new MockHttpClient()))->request(new TextToSpeech('phonon'), ['text' => ['Hello']]);
    }

    public function testItFailsWithStreamingOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Streaming text to speech results is not supported yet.');

        (new TextToSpeechClient(new MockHttpClient()))->request(new TextToSpeech('phonon'), 'Hello from Ollama.', [
            'stream' => true,
        ]);
    }

    public function testItFailsWithStreamFormatOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Streaming text to speech results is not supported yet.');

        (new TextToSpeechClient(new MockHttpClient()))->request(new TextToSpeech('phonon'), 'Hello from Ollama.', [
            'stream_format' => 'audio',
        ]);
    }

    public function testItReturnsWavAudioAsBinaryByDefault()
    {
        $result = (new TextToSpeechClient(new MockHttpClient()))->convert($this->createRawResult('fake-wav-bytes'));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('fake-wav-bytes', $result->getContent());
        $this->assertSame('audio/wav', $result->getMimeType());
    }

    public function testItReturnsPcmAudioAsBinary()
    {
        $result = (new TextToSpeechClient(new MockHttpClient()))->convert($this->createRawResult('fake-pcm-bytes'), [
            'response_format' => Format::PCM,
        ]);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('fake-pcm-bytes', $result->getContent());
        $this->assertSame('audio/pcm', $result->getMimeType());
    }

    public function testItDoesNotGuessTheMimeTypeOfAnUnknownFormat()
    {
        $result = (new TextToSpeechClient(new MockHttpClient()))->convert($this->createRawResult('fake-audio-bytes'), [
            'response_format' => 'mp3',
        ]);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertNull($result->getMimeType());
    }

    public function testItUsesTheResponseContentTypeOverTheOption()
    {
        $httpClient = new MockHttpClient(
            new MockResponse('fake-audio-bytes', ['response_headers' => ['content-type' => ['audio/pcm; charset=binary']]]),
            'http://127.0.0.1:11434',
        );

        $result = (new TextToSpeechClient(new MockHttpClient()))->convert(new RawHttpResult($httpClient->request('POST', '/v1/audio/speech')), [
            'response_format' => Format::WAV,
        ]);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio/pcm', $result->getMimeType());
    }

    public function testItThrowsOnServerError()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Ollama is overloaded');

        $httpClient = new MockHttpClient(
            new JsonMockResponse(['error' => 'Ollama is overloaded'], ['http_code' => 500]),
            'http://127.0.0.1:11434',
        );

        (new TextToSpeechClient(new MockHttpClient()))->convert(new RawHttpResult($httpClient->request('POST', '/v1/audio/speech')));
    }

    public function testItThrowsOnBadRequestWithTheOpenAiCompatibleError()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('voice "nope" not found; available voices: default');

        $httpClient = new MockHttpClient(
            new JsonMockResponse([
                'error' => [
                    'message' => 'voice "nope" not found; available voices: default',
                    'type' => 'invalid_request_error',
                    'param' => null,
                    'code' => null,
                ],
            ], ['http_code' => 400]),
            'http://127.0.0.1:11434',
        );

        (new TextToSpeechClient(new MockHttpClient()))->convert(new RawHttpResult($httpClient->request('POST', '/v1/audio/speech')));
    }

    public function testItThrowsOnBadRequestWithTheOllamaError()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('unknown voice "foo"; available voices: default, zoey');

        $httpClient = new MockHttpClient(
            new JsonMockResponse(['error' => 'unknown voice "foo"; available voices: default, zoey'], ['http_code' => 400]),
            'http://127.0.0.1:11434',
        );

        (new TextToSpeechClient(new MockHttpClient()))->convert(new RawHttpResult($httpClient->request('POST', '/v1/audio/speech')));
    }

    public function testItThrowsOnUnexpectedSuccessStatusCode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Ollama Text-to-Speech API returned an error: ""');

        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 204]), 'http://127.0.0.1:11434');

        (new TextToSpeechClient(new MockHttpClient()))->convert(new RawHttpResult($httpClient->request('POST', '/v1/audio/speech')));
    }

    private function createRawResult(string $content): RawHttpResult
    {
        $httpClient = new MockHttpClient(new MockResponse($content), 'http://127.0.0.1:11434');

        return new RawHttpResult($httpClient->request('POST', '/v1/audio/speech'));
    }
}
