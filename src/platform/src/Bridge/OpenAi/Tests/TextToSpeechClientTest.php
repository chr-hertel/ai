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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeechClient;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextToSpeechClientTest extends TestCase
{
    public function testSupportsTextToSpeechModel()
    {
        $converter = self::client();
        $model = new TextToSpeech('tts-1');

        $this->assertTrue($converter->supports($model));
    }

    public function testDoesntSupportOtherModels()
    {
        $converter = self::client();
        $model = new Model('test-model');

        $this->assertFalse($converter->supports($model));
    }

    public function testHappyCase()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/audio/speech', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            $expectedBody = '{"voice":"alloy","instruction":"Speak like a pirate","model":"tts-1","input":"Hello World!"}';
            self::assertSame($expectedBody, $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new TextToSpeech('tts-1'), 'Hello World!', [
            'voice' => 'alloy',
            'instruction' => 'Speak like a pirate',
        ]);
    }

    public function testHappyCaseWithArrayPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/audio/speech', $url);
            $body = json_decode($options['body'], true);
            self::assertSame('Hello World!', $body['input']);
            self::assertSame('tts-1', $body['model']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new TextToSpeech('tts-1'), ['text' => 'Hello World!'], [
            'voice' => 'alloy',
        ]);
    }

    public function testFailsWithoutVoiceOption()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "voice" option is required for TextToSpeech requests.');

        $httpClient = new MockHttpClient();
        $modelClient = self::client($httpClient);
        $modelClient->request(new TextToSpeech('tts-1'), 'Hello World!', [
            'instruction' => 'Speak like a pirate',
        ]);
    }

    public function testFailsWithStreamingOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Streaming text to speech results is not supported yet.');

        $httpClient = new MockHttpClient();
        $modelClient = self::client($httpClient);
        $modelClient->request(new TextToSpeech('tts-1'), 'Hello World!', [
            'voice' => 'alloy',
            'stream' => true,
        ]);
    }

    public function testThrowsOnErrorResponse()
    {
        $client = self::client(new MockHttpClient(new MockResponse('Hi Test!', ['http_code' => 403])));

        $raw = $client->request(new TextToSpeech('tts-1'), 'Hello World!', ['voice' => 'alloy']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The OpenAI Text-to-Speech API returned an error: "Hi Test!"');

        $client->convert($raw);
    }

    public function testReturnResponseAsBinary()
    {
        $client = self::client(new MockHttpClient(new MockResponse('fake-audio-bytes')));

        $binaryResult = $client->convert($client->request(new TextToSpeech('tts-1'), 'Hello World!', ['voice' => 'alloy']));

        $this->assertInstanceOf(BinaryResult::class, $binaryResult);
        $this->assertSame('fake-audio-bytes', $binaryResult->getContent());
    }

    public function testItThrowsModelNotFoundExceptionForAnUnknownModel()
    {
        $client = self::client(new MockHttpClient(new JsonMockResponse(
            ['error' => ['message' => 'The model "tts-0" does not exist.']],
            ['http_code' => 404],
        )));

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('The model "tts-0" does not exist.');

        $client->convert($client->request(new TextToSpeech('tts-0'), 'Hello World!', ['voice' => 'alloy']));
    }

    private static function client(MockHttpClient $httpClient = new MockHttpClient()): TextToSpeechClient
    {
        return new TextToSpeechClient(new HttpTransport($httpClient, 'sk-api-key'));
    }
}
