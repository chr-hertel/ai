<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\MiniMax\ChatCompletionsClient;
use Symfony\AI\Platform\Bridge\MiniMax\ImageClient;
use Symfony\AI\Platform\Bridge\MiniMax\MiniMax;
use Symfony\AI\Platform\Bridge\MiniMax\MusicClient;
use Symfony\AI\Platform\Bridge\MiniMax\SpeechClient;
use Symfony\AI\Platform\Bridge\MiniMax\VideoClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MiniMaxClientTest extends TestCase
{
    public function testItSupportsModelsDeclaringItsEndpoint()
    {
        $client = new ChatCompletionsClient(new MockHttpClient(), 'key');

        $this->assertTrue($client->supports($this->model('MiniMax-M2', Capability::INPUT_MESSAGES, ChatCompletionsClient::ENDPOINT)));
        $this->assertFalse($client->supports($this->model('image-01', Capability::TEXT_TO_IMAGE, ImageClient::ENDPOINT)));
    }

    public function testItThrowsWhenTextPayloadIsNotAnArray()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload is not an array, given "string".');

        $client = new ChatCompletionsClient(new MockHttpClient(), 'key');
        $client->request($this->model('MiniMax-M2', Capability::INPUT_MESSAGES, ChatCompletionsClient::ENDPOINT), 'foo');
    }

    public function testItGeneratesText()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.minimax.io/v1/chat/completions', $url);

            $body = json_decode($options['body'], true);
            $this->assertSame('MiniMax-M2', $body['model']);
            $this->assertSame([['role' => 'user', 'content' => 'foo']], $body['messages']);

            return new JsonMockResponse([]);
        });

        $client = new ChatCompletionsClient($httpClient, 'key');
        $client->request($this->model('MiniMax-M2', Capability::INPUT_MESSAGES, ChatCompletionsClient::ENDPOINT), [
            'messages' => [['role' => 'user', 'content' => 'foo']],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItGeneratesTextAsStream()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $body = json_decode($options['body'], true);
            $this->assertTrue($body['stream']);

            return new JsonMockResponse([]);
        });

        $client = new ChatCompletionsClient($httpClient, 'key');
        $client->request($this->model('MiniMax-M2', Capability::INPUT_MESSAGES, ChatCompletionsClient::ENDPOINT), [
            'messages' => [['role' => 'user', 'content' => 'foo']],
        ], [
            'stream' => true,
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItGeneratesSpeech()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://api.minimax.io/v1/t2a_v2', $url);

            $body = json_decode($options['body'], true);
            $this->assertSame('speech-2.6-hd', $body['model']);
            $this->assertSame('Hello world', $body['text']);
            $this->assertSame('hex', $body['output_format']);

            return new JsonMockResponse([]);
        });

        $client = new SpeechClient($httpClient, 'key');
        $client->request($this->model('speech-2.6-hd', Capability::TEXT_TO_SPEECH, SpeechClient::ENDPOINT), 'Hello world');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItGeneratesSpeechFromNormalizedTextPayload()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://api.minimax.io/v1/t2a_v2', $url);

            $body = json_decode($options['body'], true);
            $this->assertSame('Hello world', $body['text']);

            return new JsonMockResponse([]);
        });

        $client = new SpeechClient($httpClient, 'key');
        $client->request($this->model('speech-2.6-hd', Capability::TEXT_TO_SPEECH, SpeechClient::ENDPOINT), [
            'type' => 'text',
            'text' => 'Hello world',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItThrowsWhenSpeechPayloadHasNoText()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must be a string or contain a "text" key.');

        $client = new SpeechClient(new MockHttpClient(), 'key');
        $client->request($this->model('speech-2.6-hd', Capability::TEXT_TO_SPEECH, SpeechClient::ENDPOINT), [
            'type' => 'text',
        ]);
    }

    public function testItGeneratesSpeechAsynchronously()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://api.minimax.io/v1/t2a_async_v2', $url);

            $body = json_decode($options['body'], true);
            $this->assertArrayNotHasKey('async', $body);

            return new JsonMockResponse([]);
        });

        $client = new SpeechClient($httpClient, 'key');
        $client->request($this->model('speech-2.6-hd', Capability::TEXT_TO_SPEECH, SpeechClient::ENDPOINT), 'Hello world', [
            'async' => true,
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItGeneratesImage()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://api.minimax.io/v1/image_generation', $url);

            $body = json_decode($options['body'], true);
            $this->assertSame('image-01', $body['model']);
            $this->assertSame('A cat', $body['prompt']);
            $this->assertSame('base64', $body['response_format']);

            return new JsonMockResponse([]);
        });

        $client = new ImageClient($httpClient, 'key');
        $client->request($this->model('image-01', Capability::TEXT_TO_IMAGE, ImageClient::ENDPOINT), 'A cat');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRequiresLyricsToGenerateMusic()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "lyrics" option is required when generating music.');

        $client = new MusicClient(new MockHttpClient(), 'key');
        $client->request($this->model('music-1.5', Capability::MUSIC, MusicClient::ENDPOINT), 'An upbeat pop song');
    }

    public function testItGeneratesMusic()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://api.minimax.io/v1/music_generation', $url);

            $body = json_decode($options['body'], true);
            $this->assertSame('music-1.5', $body['model']);
            $this->assertSame('An upbeat pop song', $body['prompt']);
            $this->assertSame('la la la', $body['lyrics']);

            return new JsonMockResponse([]);
        });

        $client = new MusicClient($httpClient, 'key');
        $client->request($this->model('music-1.5', Capability::MUSIC, MusicClient::ENDPOINT), 'An upbeat pop song', [
            'lyrics' => 'la la la',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItGeneratesVideo()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://api.minimax.io/v1/video_generation', $url);

            $body = json_decode($options['body'], true);
            $this->assertSame('MiniMax-Hailuo-02', $body['model']);
            $this->assertSame('A cat playing piano', $body['prompt']);

            return new JsonMockResponse([]);
        });

        $client = new VideoClient($httpClient, 'key');
        $client->request($this->model('MiniMax-Hailuo-02', Capability::TEXT_TO_VIDEO, VideoClient::ENDPOINT), 'A cat playing piano');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    private function model(string $name, Capability $capability, string $endpoint): MiniMax
    {
        return new MiniMax($name, [$capability], [], [new Endpoint($endpoint)]);
    }
}
