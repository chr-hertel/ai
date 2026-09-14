<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cartesia\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cartesia\Cartesia;
use Symfony\AI\Platform\Bridge\Cartesia\TextToSpeechClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TextToSpeechClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new TextToSpeechClient(
            new MockHttpClient(),
            'my-api-key',
            'foo',
        );

        $this->assertTrue($client->supports(new Cartesia('sonic-3', [Capability::TEXT_TO_SPEECH])));
        $this->assertFalse($client->supports(new Cartesia('ink-whisper', [Capability::SPEECH_TO_TEXT])));
        $this->assertFalse($client->supports(new Cartesia('foo')));
    }

    public function testClientCannotPerformTextToSpeechOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'error' => '',
            ], [
                'http_code' => 400,
            ]),
        ]);

        $client = new TextToSpeechClient(
            $httpClient,
            'my-api-key',
            'foo',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "https://api.cartesia.ai/tts/bytes".');
        $this->expectExceptionCode(400);
        $client->request(new Cartesia('sonic-3', [Capability::TEXT_TO_SPEECH]), [
            'text' => 'bar',
        ], [
            'voice' => '6ccbfb76-1fc6-48f7-b71d-91ac6298247b', // Tessa (https://play.cartesia.ai/voices/6ccbfb76-1fc6-48f7-b71d-91ac6298247b)
            'output_format' => [
                'container' => 'mp3',
                'sample_rate' => 48000,
                'bit_rate' => 192000,
            ],
        ]);
    }

    public function testClientCanPerformTextToSpeech()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($payload): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.cartesia.ai/tts/bytes', $url);
            $this->assertContains('Authorization: Bearer my-api-key', $options['headers']);
            $this->assertContains('Cartesia-Version: foo', $options['headers']);

            $body = json_decode($options['body'], true);
            $this->assertSame('sonic-3', $body['model_id']);
            $this->assertSame('bar', $body['transcript']);
            $this->assertSame(['mode' => 'id', 'id' => '6ccbfb76-1fc6-48f7-b71d-91ac6298247b'], $body['voice']);

            return new MockResponse($payload->asBinary());
        });

        $client = new TextToSpeechClient(
            $httpClient,
            'my-api-key',
            'foo',
        );

        $client->request(new Cartesia('sonic-3', [Capability::TEXT_TO_SPEECH]), [
            'text' => 'bar',
        ], [
            'voice' => '6ccbfb76-1fc6-48f7-b71d-91ac6298247b', // Tessa (https://play.cartesia.ai/voices/6ccbfb76-1fc6-48f7-b71d-91ac6298247b)
            'output_format' => [
                'container' => 'mp3',
                'sample_rate' => 48000,
                'bit_rate' => 192000,
            ],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechWithStringPayload()
    {
        $audioFixture = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($audioFixture): MockResponse {
            $this->assertSame('POST', $method);
            $body = json_decode($options['body'], true);
            $this->assertSame('bar', $body['transcript']);

            return new MockResponse($audioFixture->asBinary());
        });

        $client = new TextToSpeechClient(
            $httpClient,
            'my-api-key',
            'foo',
        );

        $client->request(new Cartesia('sonic-3', [Capability::TEXT_TO_SPEECH]), 'bar', [
            'voice' => '6ccbfb76-1fc6-48f7-b71d-91ac6298247b',
            'output_format' => [
                'container' => 'mp3',
                'sample_rate' => 48000,
                'bit_rate' => 192000,
            ],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $audioFixture = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient(function (string $method, string $url) use ($audioFixture): MockResponse {
            $this->assertSame('https://x.example.com/tts/bytes', $url);

            return new MockResponse($audioFixture->asBinary());
        });

        $client = new TextToSpeechClient(
            $httpClient,
            'my-api-key',
            'foo',
            'https://x.example.com/',
        );

        $client->request(new Cartesia('sonic-3', [Capability::TEXT_TO_SPEECH]), 'bar', [
            'voice' => '6ccbfb76-1fc6-48f7-b71d-91ac6298247b',
            'output_format' => [
                'container' => 'mp3',
                'sample_rate' => 48000,
                'bit_rate' => 192000,
            ],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testConvertTextToSpeechResponse()
    {
        $client = new TextToSpeechClient(new MockHttpClient(), 'my-api-key', 'foo');
        $rawResult = new InMemoryRawResult([], [], new class {
            public function getContent(): string
            {
                return file_get_contents(\dirname(__DIR__, 6).'/fixtures/audio.mp3');
            }
        });

        $result = $client->convert($rawResult);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }
}
