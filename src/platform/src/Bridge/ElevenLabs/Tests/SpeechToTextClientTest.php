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
use Symfony\AI\Platform\Bridge\ElevenLabs\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\Factory;
use Symfony\AI\Platform\Bridge\ElevenLabs\Result\AdditionalFormat;
use Symfony\AI\Platform\Bridge\ElevenLabs\Result\Transcript;
use Symfony\AI\Platform\Bridge\ElevenLabs\SpeechToTextClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SpeechToTextClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new SpeechToTextClient(new MockHttpClient());

        $this->assertTrue($client->supports(new ElevenLabs('scribe_v1', [Capability::SPEECH_TO_TEXT])));
        $this->assertFalse($client->supports(new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH])));
        $this->assertFalse($client->supports(new ElevenLabs('foo')));
    }

    public function testClientCannotPerformSpeechToTextRequestWithInvalidPayload()
    {
        $client = new SpeechToTextClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array for speech-to-text request, got "string".');
        $this->expectExceptionCode(0);
        $client->request(new ElevenLabs('eleven_multilingual_v2', [Capability::SPEECH_TO_TEXT]), 'foo');
    }

    public function testClientCannotPerformSpeechToTextRequestWithoutInputAudio()
    {
        $client = new SpeechToTextClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input audio is required for speech-to-text request.');
        $client->request(new ElevenLabs('scribe_v1', [Capability::SPEECH_TO_TEXT]), []);
    }

    public function testClientCannotPerformSpeechToTextRequestWithNonArrayInputAudio()
    {
        $client = new SpeechToTextClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input audio must be an array with a "path" key for speech-to-text request.');
        $client->request(new ElevenLabs('scribe_v1', [Capability::SPEECH_TO_TEXT]), ['input_audio' => 'foo']);
    }

    public function testClientCanPerformSpeechToTextRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/speech-to-text', $url);

            return new JsonMockResponse([
                'text' => 'foo',
            ]);
        }, 'https://api.elevenlabs.io/v1/');

        $client = new SpeechToTextClient($httpClient);

        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3'));

        $client->request(new ElevenLabs('scribe_v1', [
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_TEXT,
            Capability::SPEECH_TO_TEXT,
        ]), $payload);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformSpeechToTextRequestWithExperimentalModel()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/speech-to-text', $url);

            return new JsonMockResponse([
                'text' => 'foo',
            ]);
        }, 'https://api.elevenlabs.io/v1/');

        $client = new SpeechToTextClient($httpClient);

        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3'));

        $client->request(new ElevenLabs('scribe_v1_experimental', [
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_TEXT,
            Capability::SPEECH_TO_TEXT,
        ]), $payload);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformSpeechToTextRequestWithOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/speech-to-text', $url);

            $body = '';
            $readChunk = $options['body'];
            while ('' !== $chunk = $readChunk(8192)) {
                $body .= $chunk;
            }

            $this->assertStringContainsString('Content-Disposition: form-data; name="model_id"', $body);
            $this->assertStringContainsString('Content-Disposition: form-data; name="language_code"', $body);
            $this->assertStringContainsString("language_code\"\r\n\r\npl\r\n", $body);
            $this->assertStringContainsString('Content-Disposition: form-data; name="diarize"', $body);
            $this->assertStringContainsString("diarize\"\r\n\r\ntrue\r\n", $body);
            $this->assertStringContainsString('Content-Disposition: form-data; name="tag_audio_events"', $body);
            $this->assertStringContainsString("tag_audio_events\"\r\n\r\nfalse\r\n", $body);
            $this->assertStringContainsString('Content-Disposition: form-data; name="num_speakers"', $body);
            $this->assertStringContainsString("num_speakers\"\r\n\r\n1\r\n", $body);
            $this->assertStringContainsString('Content-Disposition: form-data; name="timestamps_granularity"', $body);
            $this->assertStringContainsString("timestamps_granularity\"\r\n\r\nword\r\n", $body);
            $this->assertStringContainsString('Content-Disposition: form-data; name="additional_formats"', $body);
            $this->assertStringContainsString('{"format":"srt","include_timestamps":true}', $body);

            return new JsonMockResponse([
                'text' => 'foo',
            ]);
        }, 'https://api.elevenlabs.io/v1/');

        $client = new SpeechToTextClient($httpClient);

        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3'));

        $client->request(new ElevenLabs('scribe_v2', [
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_TEXT,
            Capability::SPEECH_TO_TEXT,
        ]), $payload, [
            'language_code' => 'pl',
            'tag_audio_events' => false,
            'num_speakers' => 1,
            'diarize' => true,
            'timestamps_granularity' => 'word',
            'additional_formats' => [
                ['format' => 'srt', 'include_timestamps' => true],
            ],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testInvokeOptionOverridesModelDefaultOptionForSpeechToText()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.elevenlabs.io/v1/speech-to-text', $url);

            $body = '';
            $readChunk = $options['body'];
            while ('' !== $chunk = $readChunk(8192)) {
                $body .= $chunk;
            }

            // Framework convention: Provider::invoke() merges options as
            // array_merge($model->getOptions(), $options), so an option passed at
            // invoke time must win over the same key configured as a model default.
            // Here "pl" is the invoke-time value and "de" the model default.
            $this->assertStringContainsString("language_code\"\r\n\r\npl\r\n", $body);
            $this->assertStringNotContainsString("language_code\"\r\n\r\nde\r\n", $body);

            return new JsonMockResponse([
                'text' => 'foo',
            ]);
        }, 'https://api.elevenlabs.io/v1/');

        $platform = Factory::createPlatform(apiKey: 'sk-test', httpClient: $httpClient);

        $model = new ElevenLabs('scribe_v2', [
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_TEXT,
            Capability::SPEECH_TO_TEXT,
        ], [
            'language_code' => 'de',
        ]);

        $result = $platform->invoke($model, Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3'), [
            'language_code' => 'pl',
        ]);
        $result->asText();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testConvertSpeechToTextResponse()
    {
        $client = new SpeechToTextClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult([
            'text' => 'Hello there',
        ], [], new class {
            public function getStatusCode(): int
            {
                return 200;
            }
        });

        $result = $client->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello there', $result->getContent());
    }

    public function testConvertSpeechToTextResponseExposesAdditionalFormats()
    {
        $client = new SpeechToTextClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult([
            'text' => 'Hello there',
            'additional_formats' => [
                [
                    'requested_format' => 'srt',
                    'file_extension' => '.srt',
                    'content_type' => 'text/plain',
                    'is_base64_encoded' => false,
                    'content' => "1\n00:00:00,000 --> 00:00:01,000\nHello there\n",
                ],
                [
                    'requested_format' => 'txt',
                    'file_extension' => '.txt',
                    'content_type' => 'text/plain',
                    'is_base64_encoded' => true,
                    'content' => base64_encode('Hello there'),
                ],
            ],
        ], [], new class {
            public function getStatusCode(): int
            {
                return 200;
            }
        });

        $result = $client->convert($rawResult, [
            'additional_formats' => [
                ['format' => 'srt', 'include_timestamps' => true],
            ],
        ]);

        $this->assertInstanceOf(ObjectResult::class, $result);

        $transcript = $this->extractTranscript($result);
        $this->assertSame('Hello there', $transcript->getText());
        $this->assertSame(
            "1\n00:00:00,000 --> 00:00:01,000\nHello there\n",
            $transcript->asSubRipText(),
        );
        $this->assertSame(
            "1\n00:00:00,000 --> 00:00:01,000\nHello there\n",
            $transcript->getAdditionalFormat('srt'),
        );
        $this->assertSame('Hello there', $transcript->getAdditionalFormat('txt'));
        $this->assertNull($transcript->getAdditionalFormat('html'));

        $additionalFormats = $transcript->getAdditionalFormats();
        $this->assertContainsOnlyInstancesOf(AdditionalFormat::class, $additionalFormats);
        $this->assertCount(2, $additionalFormats);

        $srt = $additionalFormats[0];
        $this->assertSame('srt', $srt->getRequestedFormat());
        $this->assertSame('.srt', $srt->getFileExtension());
        $this->assertSame('text/plain', $srt->getContentType());
        $this->assertFalse($srt->isBase64Encoded());
        $this->assertSame("1\n00:00:00,000 --> 00:00:01,000\nHello there\n", $srt->getDecodedContent());

        $txt = $additionalFormats[1];
        $this->assertTrue($txt->isBase64Encoded());
        $this->assertSame('Hello there', $txt->getDecodedContent());
    }

    public function testGetDecodedContentThrowsRuntimeExceptionOnInvalidBase64Payload()
    {
        $format = AdditionalFormat::fromArray([
            'requested_format' => 'srt',
            'file_extension' => '.srt',
            'content_type' => 'text/plain',
            'is_base64_encoded' => true,
            'content' => 'this-is-not-valid-base64!!!',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The base64-encoded content of the "srt" additional format could not be decoded.');
        $format->getDecodedContent();
    }

    public function testConvertSpeechToTextResponseWithoutAdditionalFormatsOptionReturnsTextResult()
    {
        $client = new SpeechToTextClient(new MockHttpClient());
        $rawResult = new InMemoryRawResult([
            'text' => 'Hello there',
            // The API happens to return additional formats, but none were requested.
            'additional_formats' => [
                [
                    'requested_format' => 'srt',
                    'file_extension' => '.srt',
                    'content_type' => 'text/plain',
                    'is_base64_encoded' => false,
                    'content' => "1\n00:00:00,000 --> 00:00:01,000\nHello there\n",
                ],
            ],
        ], [], new class {
            public function getStatusCode(): int
            {
                return 200;
            }
        });

        $result = $client->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello there', $result->getContent());
    }

    public function testConvertThrowsExceptionWithDetailedErrorMessage()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'detail' => [
                    'status' => 'invalid_api_key',
                    'message' => 'Invalid API key',
                ],
            ], ['http_code' => 401]),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.elevenlabs.io/v1/speech-to-text'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API key');
        (new SpeechToTextClient(new MockHttpClient()))->convert($rawResult);
    }

    public function testConvertThrowsExceptionWithoutErrorMessage()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 500]),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.elevenlabs.io/v1/speech-to-text'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The ElevenLabs API returned a non-successful status code "500".');
        (new SpeechToTextClient(new MockHttpClient()))->convert($rawResult);
    }

    private function extractTranscript(ResultInterface $result): Transcript
    {
        \assert($result instanceof ObjectResult);
        \assert($result->getContent() instanceof Transcript);

        return $result->getContent();
    }
}
