<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\Factory;
use Symfony\AI\Platform\Bridge\ElevenLabs\TextToSpeechClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TextToSpeechClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new TextToSpeechClient(new MockHttpClient());

        $this->assertTrue($client->supports(new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH])));
        $this->assertFalse($client->supports(new ElevenLabs('scribe_v1', [Capability::SPEECH_TO_TEXT])));
        $this->assertFalse($client->supports(new ElevenLabs('foo')));
    }

    public function testClientCannotPerformTextToSpeechRequestWithoutVoice()
    {
        $client = new TextToSpeechClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The voice option is required.');
        $client->request(new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH]), 'foo');
    }

    public function testInvokeOptionOverridesModelDefaultOptionForTextToSpeech()
    {
        $audioFixture = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($audioFixture): MockResponse {
            $this->assertSame('POST', $method);
            $body = json_decode($options['body'], true);

            // Framework convention: Provider::invoke() merges options as
            // array_merge($model->getOptions(), $options), so an option passed at
            // invoke time must win over the same key configured as a model default.
            $this->assertSame([
                'stability' => 0.9,
            ], $body['voice_settings']);

            return new MockResponse($audioFixture->asBinary());
        }, 'https://api.elevenlabs.io/v1/');

        $platform = Factory::createPlatform(apiKey: 'sk-test', httpClient: $httpClient);

        $model = new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH], [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
            'voice_settings' => ['stability' => 0.1],
        ]);

        $result = $platform->invoke($model, 'foo', [
            'voice_settings' => ['stability' => 0.9],
        ]);
        $result->asBinary();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCannotPerformTextToSpeechRequestWithoutValidPayload()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([]),
        ], 'https://api.elevenlabs.io/v1/');

        $client = new TextToSpeechClient($mockHttpClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must contain a "text" key');
        $this->expectExceptionCode(0);
        $client->request(new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH], [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
        ]), []);
    }

    public function testClientCanPerformTextToSpeechRequest()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient(function (string $method, string $url) use ($payload): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/text-to-speech/Dslrhjl3ZpzrctukrQSN', $url);

            return new MockResponse($payload->asBinary());
        }, 'https://api.elevenlabs.io/v1/');

        $client = new TextToSpeechClient($httpClient);

        $client->request(new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH], [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
        ]), [
            'text' => 'foo',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechRequestWithStringPayload()
    {
        $audioFixture = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($audioFixture): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/text-to-speech/Dslrhjl3ZpzrctukrQSN', $url);
            $body = json_decode($options['body'], true);
            $this->assertSame('foo', $body['text']);

            return new MockResponse($audioFixture->asBinary());
        }, 'https://api.elevenlabs.io/v1/');

        $client = new TextToSpeechClient($httpClient);

        $client->request(new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH], [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
        ]), 'foo');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechRequestWhenVoiceKeyIsProvidedAsRequestOption()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient(function (string $method, string $url) use ($payload): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/text-to-speech/Dslrhjl3ZpzrctukrQSN', $url);

            return new MockResponse($payload->asBinary());
        }, 'https://api.elevenlabs.io/v1/');

        $client = new TextToSpeechClient($httpClient);

        $client->request(new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH]), [
            'text' => 'foo',
        ], [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechRequestAsStream()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient(function (string $method, string $url) use ($payload): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/text-to-speech/Dslrhjl3ZpzrctukrQSN/stream', $url);

            return new MockResponse($payload->asBinary());
        }, 'https://api.elevenlabs.io/v1/');

        $client = new TextToSpeechClient($httpClient);

        $result = $client->request(new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH], [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
            'stream' => true,
        ]), [
            'text' => 'foo',
        ]);

        $this->assertInstanceOf(RawHttpResult::class, $result);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechRequestAsStreamVoiceKeyIsProvidedAsRequestOption()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient(function (string $method, string $url) use ($payload): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/text-to-speech/Dslrhjl3ZpzrctukrQSN/stream', $url);

            return new MockResponse($payload->asBinary());
        }, 'https://api.elevenlabs.io/v1/');

        $client = new TextToSpeechClient($httpClient);

        $result = $client->request(new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH]), [
            'text' => 'foo',
        ], [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
            'stream' => true,
        ]);

        $this->assertInstanceOf(RawHttpResult::class, $result);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechRequestWithExtraApiOptions()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($payload) {
            $this->assertSame('POST', $method);
            $this->assertArrayHasKey('body', $options);
            $body = json_decode($options['body'], true);
            $this->assertArrayHasKey('voice_settings', $body);
            $this->assertArrayNotHasKey('voice', $body);
            $this->assertArrayNotHasKey('stream', $body);
            $this->assertSame([
                'stability' => 0.5,
                'use_speaker_boost' => 1,
                'similarity_boost' => 0.7,
                'style' => 0.2,
                'speed' => 1.2,
            ], $body['voice_settings']);

            return new MockResponse($payload->asBinary());
        }, 'https://api.elevenlabs.io/v1/');

        $client = new TextToSpeechClient($httpClient);

        $client->request(new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH]), [
            'text' => 'foo',
        ], [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
            'voice_settings' => [
                'stability' => 0.5,
                'use_speaker_boost' => 1,
                'similarity_boost' => 0.7,
                'style' => 0.2,
                'speed' => 1.2,
            ],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testRequestDoesNotThrowOnHttpErrorBeforeConversion()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['detail' => ['message' => 'Invalid API key']], ['http_code' => 401]),
        ], 'https://api.elevenlabs.io/v1/');

        $client = new TextToSpeechClient($httpClient);

        $result = $client->request(new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH]), 'foo', [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API key');
        $client->convert($result);
    }

    public function testConvertTextToSpeechAsStreamResponse()
    {
        $client = new TextToSpeechClient(new MockHttpClient([], 'https://api.elevenlabs.io/v1/text-to-speech/JBFqnCBsd6RMkjVDRZzb/stream'));
        $rawResult = new InMemoryRawResult([], [], MockResponse::fromFile(\dirname(__DIR__).'/Tests/Fixtures/audio.mp3', [
            'url' => 'https://api.elevenlabs.io/v1/text-to-speech/JBFqnCBsd6RMkjVDRZzb/stream',
        ]));

        $result = $client->convert($rawResult, [
            'stream' => true,
        ]);

        $this->assertInstanceOf(StreamResult::class, $result);
    }

    public function testConvertTextToSpeechResponse()
    {
        $client = new TextToSpeechClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult([], [], new class {
            public function getStatusCode(): int
            {
                return 200;
            }

            public function getContent(): string
            {
                $content = file_get_contents(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

                if (!$content) {
                    throw new RuntimeException('Failed to load audio file for text-to-speech response.');
                }

                return $content;
            }
        });

        $result = $client->convert($rawResult);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testConvertThrowsExceptionWithDetailedErrorMessage()
    {
        $client = new TextToSpeechClient(new MockHttpClient());
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'detail' => [
                    'type' => 'payment_required',
                    'code' => 'paid_plan_required',
                    'message' => 'Free users cannot use library voices via the API. Please upgrade your subscription to use this voice.',
                    'status' => 'payment_required',
                    'request_id' => 'd79eff6fb3690c29ed6883da9fce3159',
                ],
            ], ['http_code' => 402]),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.elevenlabs.io/v1/text-to-speech'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Free users cannot use library voices via the API. Please upgrade your subscription to use this voice.');
        $this->expectExceptionCode(0);
        $client->convert($rawResult);
    }

    public function testConvertThrowsExceptionWithoutErrorMessage()
    {
        $client = new TextToSpeechClient(new MockHttpClient());
        $httpClient = new MockHttpClient([
            new MockResponse(
                '',
                ['http_code' => 500]
            ),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.elevenlabs.io/v1/text-to-speech'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The ElevenLabs API returned a non-successful status code "500".');
        $this->expectExceptionCode(0);
        $client->convert($rawResult);
    }
}
