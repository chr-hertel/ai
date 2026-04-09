<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Deepgram\DeepgramPayload;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class DeepgramPayloadTest extends TestCase
{
    public function testTextToSpeechAcceptsRawString()
    {
        $payload = new DeepgramPayload('Hello world');

        $this->assertSame('Hello world', $payload->asTextToSpeechPayload());
    }

    public function testTextToSpeechAcceptsNormalizedArray()
    {
        $payload = new DeepgramPayload(['type' => 'text', 'text' => 'Hello world']);

        $this->assertSame('Hello world', $payload->asTextToSpeechPayload());
    }

    public function testTextToSpeechRejectsArrayWithoutTextKey()
    {
        $payload = new DeepgramPayload(['type' => 'text']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The text-to-speech payload must contain a "text" key.');

        $payload->asTextToSpeechPayload();
    }

    public function testTextToSpeechRejectsNonStringText()
    {
        $payload = new DeepgramPayload(['type' => 'text', 'text' => ['Hello']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "text" key of the text-to-speech payload must be a string.');

        $payload->asTextToSpeechPayload();
    }

    public function testGetAudioBinaryDecodesBase64()
    {
        $bytes = "\x00\x01\x02RIFF";
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => [
                'data' => base64_encode($bytes),
                'format' => 'wav',
            ],
        ]);

        $this->assertSame($bytes, $payload->getAudioBinary());
    }

    public function testGetAudioBinaryRejectsMissingInputAudio()
    {
        $payload = new DeepgramPayload(['type' => 'input_audio']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text payload must contain an "input_audio" entry.');

        $payload->getAudioBinary();
    }

    public function testGetAudioBinaryRejectsMissingDataKey()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['format' => 'mp3'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text payload must contain an "input_audio.data" base64-encoded string.');

        $payload->getAudioBinary();
    }

    public function testGetAudioBinaryRejectsInvalidBase64()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['data' => '!!!not-base64!!!', 'format' => 'mp3'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "input_audio.data" entry must be a valid base64-encoded string.');

        $payload->getAudioBinary();
    }

    public function testGetAudioBinaryRejectsRawString()
    {
        $payload = new DeepgramPayload('plain text');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text payload must be a normalized audio array, raw string given.');

        $payload->getAudioBinary();
    }

    #[DataProvider('provideMimeTypeMappings')]
    public function testGetAudioMimeTypeMapping(string $format, string $expected)
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['data' => base64_encode('x'), 'format' => $format],
        ]);

        $this->assertSame($expected, $payload->getAudioMimeType());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideMimeTypeMappings(): iterable
    {
        yield 'mp3' => ['mp3', 'audio/mpeg'];
        yield 'wav' => ['wav', 'audio/wav'];
        yield 'ogg' => ['ogg', 'audio/ogg'];
        yield 'flac' => ['flac', 'audio/flac'];
        yield 'webm' => ['webm', 'audio/webm'];
        yield 'aac' => ['aac', 'audio/aac'];
        yield 'mulaw' => ['mulaw', 'audio/x-mulaw'];
        yield 'unknown passthrough' => ['audio/x-custom', 'audio/x-custom'];
    }

    #[DataProvider('provideMimeTypeFallbacks')]
    public function testGetAudioMimeTypeFallsBackToOctetStream(mixed $format)
    {
        $inputAudio = ['data' => base64_encode('x')];
        if ('missing' !== $format) {
            $inputAudio['format'] = $format;
        }

        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => $inputAudio,
        ]);

        $this->assertSame('application/octet-stream', $payload->getAudioMimeType());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideMimeTypeFallbacks(): iterable
    {
        yield 'null format' => [null];
        yield 'empty format' => [''];
        yield 'non-string format' => [123];
        yield 'missing format' => ['missing'];
    }

    public function testIsUrlBased()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['url' => 'https://example.com/audio.mp3'],
        ]);

        $this->assertTrue($payload->isUrlBased());
        $this->assertSame('https://example.com/audio.mp3', $payload->getAudioUrl());
    }

    public function testIsUrlBasedReturnsFalseForBinaryPayload()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['data' => base64_encode('x'), 'format' => 'mp3'],
        ]);

        $this->assertFalse($payload->isUrlBased());
    }

    public function testGetAudioUrlRejectsNonHttpScheme()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['url' => 'file:///etc/passwd'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text URL must use "http" or "https" scheme, "file" given.');

        $payload->getAudioUrl();
    }

    public function testGetAudioUrlRejectsDataUrls()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['url' => 'data:mp3;base64,AAAA'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text URL must use "http" or "https" scheme, "data" given.');

        $payload->getAudioUrl();
    }

    public function testGetAudioUrlRejectsEmptyString()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['url' => ''],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "url" entry of the speech-to-text payload must be a non-empty string.');

        $payload->getAudioUrl();
    }

    public function testGetAudioUrlAcceptsUppercaseScheme()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['url' => 'HTTPS://example.com/audio.mp3'],
        ]);

        $this->assertSame('HTTPS://example.com/audio.mp3', $payload->getAudioUrl());
    }

    public function testGetAudioUrlRejectsNonStringUrl()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['url' => 123],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "url" entry of the speech-to-text payload must be a non-empty string.');

        $payload->getAudioUrl();
    }

    public function testGetAudioUrlRejectsRawStringPayload()
    {
        $payload = new DeepgramPayload('plain text');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text payload does not contain a remote URL.');

        $payload->getAudioUrl();
    }

    public function testGetAudioUrlRejectsPayloadWithoutUrlEntry()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['data' => base64_encode('x')],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text payload does not contain a remote URL.');

        $payload->getAudioUrl();
    }

    public function testIsUrlBasedReturnsFalseForRawStringPayload()
    {
        $payload = new DeepgramPayload('plain text');

        $this->assertFalse($payload->isUrlBased());
    }

    public function testGetAudioPathReturnsTransportedPath()
    {
        $payload = new DeepgramPayload([
            'type' => 'input_audio',
            'input_audio' => ['data' => base64_encode('x'), 'path' => '/tmp/audio.mp3', 'format' => 'mp3'],
        ]);

        $this->assertSame('/tmp/audio.mp3', $payload->getAudioPath());
    }

    /**
     * @param array<int|string, mixed>|string $raw
     */
    #[DataProvider('providePathlessPayloads')]
    public function testGetAudioPathReturnsNullWithoutUsablePath(array|string $raw)
    {
        $payload = new DeepgramPayload($raw);

        $this->assertNull($payload->getAudioPath());
    }

    /**
     * @return iterable<string, array{array<int|string, mixed>|string}>
     */
    public static function providePathlessPayloads(): iterable
    {
        yield 'raw string payload' => ['plain text'];
        yield 'missing input_audio' => [['type' => 'input_audio']];
        yield 'null path' => [['type' => 'input_audio', 'input_audio' => ['data' => 'eA==', 'path' => null]]];
        yield 'empty path' => [['type' => 'input_audio', 'input_audio' => ['data' => 'eA==', 'path' => '']]];
        yield 'non-string path' => [['type' => 'input_audio', 'input_audio' => ['data' => 'eA==', 'path' => 42]]];
    }
}
