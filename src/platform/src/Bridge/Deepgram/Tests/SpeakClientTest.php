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
use Symfony\AI\Platform\Bridge\Deepgram\Deepgram;
use Symfony\AI\Platform\Bridge\Deepgram\ListenClient;
use Symfony\AI\Platform\Bridge\Deepgram\SpeakClient;
use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SpeakClientTest extends TestCase
{
    public function testEndpointIdentifier()
    {
        $this->assertSame('deepgram.speak', (new SpeakClient(new MockHttpClient()))->endpoint());
    }

    public function testSupportsOnlySpeakEndpoint()
    {
        $client = new SpeakClient(new MockHttpClient());

        $this->assertTrue($client->supports(new Deepgram('aura-2-thalia-en', [], [], [new Endpoint(SpeakClient::ENDPOINT)])));
        $this->assertFalse($client->supports(new Deepgram('nova-3', [], [], [new Endpoint(ListenClient::ENDPOINT)])));
        $this->assertFalse($client->supports(new Model('gpt-4')));
    }

    public function testTextToSpeechSendsModelAsQueryParamAndTextInBody()
    {
        $capturedMethod = '';
        $capturedUrl = '';
        $capturedBody = '';
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedBody) {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $body = $options['body'] ?? '';
            $capturedBody = \is_string($body) ? $body : '';

            return new MockResponse('binary-mp3-payload', ['response_headers' => ['content-type' => 'audio/mpeg']]);
        }, 'https://api.deepgram.com/v1/');

        $client = new SpeakClient($httpClient);
        $client->request(new Deepgram('aura-2-thalia-en', [], [], [new Endpoint(SpeakClient::ENDPOINT)]), ['text' => 'Hello']);

        $this->assertSame('POST', $capturedMethod);
        $this->assertSame('/v1/speak', parse_url($capturedUrl, \PHP_URL_PATH));
        $this->assertStringContainsString('model=aura-2-thalia-en', $capturedUrl);
        $this->assertSame('{"text":"Hello"}', $capturedBody);
    }

    public function testTextToSpeechForwardsExtraOptionsAsQueryParams()
    {
        $capturedUrl = '';
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new MockResponse('audio');
        }, 'https://api.deepgram.com/v1/');

        $client = new SpeakClient($httpClient);
        $client->request(
            new Deepgram('aura-2-thalia-en', [], [], [new Endpoint(SpeakClient::ENDPOINT)]),
            ['text' => 'Hi'],
            ['encoding' => 'linear16', 'sample_rate' => 24000]
        );

        $this->assertStringContainsString('encoding=linear16', $capturedUrl);
        $this->assertStringContainsString('sample_rate=24000', $capturedUrl);
    }

    public function testStreamOptionIsNotForwardedToTheApi()
    {
        $capturedUrl = '';
        $capturedBuffer = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBuffer) {
            $capturedUrl = $url;
            $capturedBuffer = $options['buffer'] ?? null;

            return new MockResponse('audio');
        }, 'https://api.deepgram.com/v1/');

        $client = new SpeakClient($httpClient);
        $client->request(
            new Deepgram('aura-2-thalia-en', [], [], [new Endpoint(SpeakClient::ENDPOINT)]),
            ['text' => 'Hi'],
            ['stream' => true, 'encoding' => 'linear16'],
        );

        $this->assertStringContainsString('encoding=linear16', $capturedUrl);
        $this->assertStringNotContainsString('stream', $capturedUrl);
        $this->assertFalse($capturedBuffer);
    }

    public function testRejectsNonHttpRawResult()
    {
        $client = new SpeakClient(new MockHttpClient());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported raw result of type');

        $client->convert(new InMemoryRawResult(['content' => 'audio']));
    }

    public function testReturnsBinaryResultForSpeak()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('audio-bytes', ['response_headers' => ['content-type' => 'audio/mpeg']]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $result = (new SpeakClient($httpClient))->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio-bytes', $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testBinaryResultFallsBackToAudioMpegWithoutContentTypeHeader()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('audio-bytes'),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $result = (new SpeakClient($httpClient))->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testStreamResultYieldsBinaryDeltas()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(['chunk1', 'chunk2', 'chunk3']),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $result = (new SpeakClient($httpClient))->convert(new RawHttpResult($response), ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $content = '';
        foreach ($result->getContent() as $delta) {
            $this->assertInstanceOf(BinaryDelta::class, $delta);
            $content .= $delta->getData();
        }

        $this->assertSame('chunk1chunk2chunk3', $content);
    }

    public function testFallsBackToStatusCodeOnNonJsonError()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('plain error body', ['http_code' => 500]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Deepgram API returned a non-successful status code "500".');

        (new SpeakClient($httpClient))->convert(new RawHttpResult($response));
    }

    /**
     * @param class-string<\Throwable> $expectedException
     */
    #[DataProvider('provideTypedExceptionStatuses')]
    public function testMapsHttpStatusToTypedException(int $statusCode, string $expectedException)
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['err_msg' => 'Something went wrong.'], ['http_code' => $statusCode]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $this->expectException($expectedException);

        (new SpeakClient($httpClient))->convert(new RawHttpResult($response));
    }

    /**
     * @return iterable<string, array{int, class-string<\Throwable>}>
     */
    public static function provideTypedExceptionStatuses(): iterable
    {
        yield '400 bad request' => [400, BadRequestException::class];
        yield '401 unauthorized' => [401, AuthenticationException::class];
        yield '404 not found' => [404, ModelNotFoundException::class];
        yield '429 rate limited' => [429, RateLimitExceededException::class];
        yield '500 server error' => [500, ServerException::class];
        yield '503 server error' => [503, ServerException::class];
    }
}
