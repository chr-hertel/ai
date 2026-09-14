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
use Symfony\AI\Platform\Bridge\Mistral\TextToSpeech;
use Symfony\AI\Platform\Bridge\Mistral\TextToSpeech\Format;
use Symfony\AI\Platform\Bridge\Mistral\TextToSpeechClient;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class TextToSpeechClientTest extends TestCase
{
    public function testItSupportsTextToSpeechModel()
    {
        $client = new TextToSpeechClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new TextToSpeech('voxtral-mini-tts-3-0')));
    }

    public function testItDoesNotSupportMistralModel()
    {
        $client = new TextToSpeechClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Mistral('mistral-large-latest')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mistral.ai/v1/audio/speech', $url);
            $this->assertSame('Authorization: Bearer test-key', $options['normalized_headers']['authorization'][0]);
            $this->assertSame([
                'response_format' => 'wav',
                'voice_id' => 'gb_jane_neutral',
                'model' => 'voxtral-mini-tts-3-0',
                'input' => 'Hello world',
            ], json_decode($options['body'], true));

            return new JsonMockResponse(['audio_data' => base64_encode('audio')]);
        });

        $client = new TextToSpeechClient($httpClient, 'test-key');

        $client->request(new TextToSpeech('voxtral-mini-tts-3-0'), ['type' => 'text', 'text' => 'Hello world'], [
            'voice' => 'gb_jane_neutral',
            'response_format' => 'wav',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItAcceptsAStringPayload()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('Hello world', json_decode($options['body'], true)['input']);

            return new JsonMockResponse(['audio_data' => base64_encode('audio')]);
        });

        $client = new TextToSpeechClient($httpClient, 'test-key');

        $client->request(new TextToSpeech('voxtral-mini-tts-3-0'), 'Hello world', ['voice' => 'gb_jane_neutral']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUsesTheVoiceFromTheModelOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('voice_592', json_decode($options['body'], true)['voice_id']);

            return new JsonMockResponse(['audio_data' => base64_encode('audio')]);
        });

        $client = new TextToSpeechClient($httpClient, 'test-key');

        $client->request(new TextToSpeech('voxtral-mini-tts-3-0', options: ['voice' => 'voice_592']), 'Bonjour');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItAcceptsAReferenceAudioInsteadOfAVoice()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame('cmVmZXJlbmNl', $body['ref_audio']);
            $this->assertArrayNotHasKey('voice_id', $body);

            return new JsonMockResponse(['audio_data' => base64_encode('audio')]);
        });

        $client = new TextToSpeechClient($httpClient, 'test-key');

        $client->request(new TextToSpeech('voxtral-mini-tts-3-0'), 'Hello world', ['ref_audio' => 'cmVmZXJlbmNl']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItHonorsCustomBaseUrl()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('https://example.com/v1/audio/speech', $url);

            return new JsonMockResponse(['audio_data' => base64_encode('audio')]);
        });

        $client = new TextToSpeechClient($httpClient, 'test-key', 'https://example.com/');

        $client->request(new TextToSpeech('voxtral-mini-tts-3-0'), 'Hello world', ['voice' => 'gb_jane_neutral']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRequiresAVoiceOrAReferenceAudio()
    {
        $client = new TextToSpeechClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Either the "voice" or the "ref_audio" option is required for Mistral text to speech requests.');

        $client->request(new TextToSpeech('voxtral-mini-tts-3-0'), 'Hello world');
    }

    public function testItRequiresAText()
    {
        $client = new TextToSpeechClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must contain a "text" key.');

        $client->request(new TextToSpeech('voxtral-mini-tts-3-0'), [], ['voice' => 'gb_jane_neutral']);
    }

    public function testItRejectsStreaming()
    {
        $client = new TextToSpeechClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Streaming text to speech results is not supported yet.');

        $client->request(new TextToSpeech('voxtral-mini-tts-3-0'), 'Hello world', ['voice' => 'gb_jane_neutral', 'stream' => true]);
    }

    public function testItDecodesTheAudioData()
    {
        $result = (new TextToSpeechClient(new MockHttpClient(), 'test-key'))->convert($this->rawResult(new JsonMockResponse(['audio_data' => base64_encode('mp3-bytes')])));

        $this->assertSame('mp3-bytes', $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testItDerivesTheMimeTypeFromTheResponseFormat()
    {
        $result = (new TextToSpeechClient(new MockHttpClient(), 'test-key'))->convert(
            $this->rawResult(new JsonMockResponse(['audio_data' => base64_encode('wav-bytes')])),
            ['response_format' => Format::WAV],
        );

        $this->assertSame('audio/wav', $result->getMimeType());
    }

    public function testItLeavesTheMimeTypeUnsetForRawFormats()
    {
        $result = (new TextToSpeechClient(new MockHttpClient(), 'test-key'))->convert(
            $this->rawResult(new JsonMockResponse(['audio_data' => base64_encode('pcm-bytes')])),
            ['response_format' => Format::PCM],
        );

        $this->assertNull($result->getMimeType());
    }

    public function testItThrowsWhenAudioDataIsMissing()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain audio data.');

        (new TextToSpeechClient(new MockHttpClient(), 'test-key'))->convert($this->rawResult(new JsonMockResponse(['foo' => 'bar'])));
    }

    public function testItThrowsOnBadRequest()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Either ref_audio or voice must be provided.');

        (new TextToSpeechClient(new MockHttpClient(), 'test-key'))->convert($this->rawResult(new JsonMockResponse(
            ['message' => 'Either ref_audio or voice must be provided.'],
            ['http_code' => 400],
        )));
    }

    private function rawResult(JsonMockResponse $response): RawHttpResult
    {
        return new RawHttpResult((new MockHttpClient($response))->request('POST', 'https://api.mistral.ai/v1/audio/speech'));
    }
}
