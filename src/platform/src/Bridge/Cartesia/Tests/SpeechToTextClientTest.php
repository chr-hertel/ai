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
use Symfony\AI\Platform\Bridge\Cartesia\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\Cartesia\SpeechToTextClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class SpeechToTextClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new SpeechToTextClient(
            new MockHttpClient(),
            'my-api-key',
            'foo',
        );

        $this->assertTrue($client->supports(new Cartesia('ink-whisper', [Capability::SPEECH_TO_TEXT])));
        $this->assertFalse($client->supports(new Cartesia('sonic-3', [Capability::TEXT_TO_SPEECH])));
        $this->assertFalse($client->supports(new Cartesia('foo')));
    }

    public function testClientCannotPerformSpeechToTextOnInvalidResponse()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $normalizer = new AudioNormalizer();

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'error' => '',
            ], [
                'http_code' => 400,
            ]),
        ]);

        $client = new SpeechToTextClient(
            $httpClient,
            'my-api-key',
            'foo',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "https://api.cartesia.ai/stt".');
        $this->expectExceptionCode(400);
        $client->request(new Cartesia('ink-whisper', [Capability::SPEECH_TO_TEXT]), $normalizer->normalize($payload));
    }

    public function testClientCanPerformSpeechToText()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $normalizer = new AudioNormalizer();

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.cartesia.ai/stt', $url);
            $this->assertContains('Authorization: Bearer my-api-key', $options['headers']);
            $this->assertContains('Cartesia-Version: foo', $options['headers']);

            $body = '';
            while ('' !== $chunk = $options['body'](8192)) {
                $body .= $chunk;
            }

            $this->assertStringContainsString("name=\"model\"\r\n\r\nink-whisper\r\n", $body);
            $this->assertStringContainsString("name=\"timestamp_granularities[]\"\r\n\r\nword\r\n", $body);
            $this->assertStringContainsString('name="file"', $body);

            return new JsonMockResponse([
                'text' => 'Hello there',
            ]);
        });

        $client = new SpeechToTextClient(
            $httpClient,
            'my-api-key',
            'foo',
        );

        $client->request(new Cartesia('ink-whisper', [Capability::SPEECH_TO_TEXT]), $normalizer->normalize($payload));

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testConvertSpeechToTextResponse()
    {
        $client = new SpeechToTextClient(new MockHttpClient(), 'my-api-key', 'foo');
        $rawResult = new InMemoryRawResult([
            'text' => 'Hello there',
        ]);

        $result = $client->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello there', $result->getContent());
    }
}
