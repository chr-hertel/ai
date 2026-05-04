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
use Symfony\AI\Platform\Bridge\Deepgram\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\Deepgram\Deepgram;
use Symfony\AI\Platform\Bridge\Deepgram\ListenClient;
use Symfony\AI\Platform\Bridge\Deepgram\SpeakClient;
use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ListenClientTest extends TestCase
{
    public function testEndpointIdentifier()
    {
        $this->assertSame('deepgram.listen', (new ListenClient(new MockHttpClient()))->endpoint());
    }

    public function testSupportsOnlyListenEndpoint()
    {
        $client = new ListenClient(new MockHttpClient());

        $this->assertTrue($client->supports(new Deepgram('nova-3', [], [], [new Endpoint(ListenClient::ENDPOINT)])));
        $this->assertFalse($client->supports(new Deepgram('aura-2-thalia-en', [], [], [new Endpoint(SpeakClient::ENDPOINT)])));
        $this->assertFalse($client->supports(new Model('gpt-4')));
    }

    public function testSpeechToTextStreamsAudioFromPath()
    {
        $capturedMethod = '';
        $capturedUrl = '';
        $capturedBody = null;
        $contentTypeHeader = '';
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedBody, &$contentTypeHeader) {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedBody = $options['body'] ?? null;
            $headers = $options['headers'] ?? [];
            if (\is_array($headers)) {
                foreach ($headers as $header) {
                    if (\is_string($header) && str_starts_with(strtolower($header), 'content-type:')) {
                        $contentTypeHeader = $header;
                        break;
                    }
                }
            }

            return new MockResponse('{"results":{"channels":[{"alternatives":[{"transcript":"hello"}]}]}}');
        }, 'https://api.deepgram.com/v1/');

        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(__DIR__.'/Fixtures/audio.mp3'));

        $client = new ListenClient($httpClient);
        $client->request(new Deepgram('nova-3', [], [], [new Endpoint(ListenClient::ENDPOINT)]), $payload);

        $this->assertSame('POST', $capturedMethod);
        $this->assertSame('/v1/listen', parse_url($capturedUrl, \PHP_URL_PATH));
        $this->assertIsResource($capturedBody);
        rewind($capturedBody);
        $this->assertSame((string) file_get_contents(__DIR__.'/Fixtures/audio.mp3'), stream_get_contents($capturedBody));
        $this->assertStringContainsString('audio/mpeg', $contentTypeHeader);
    }

    public function testSpeechToTextFallsBackToDecodedBase64WithoutPath()
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'] ?? null;

            return new MockResponse('{"results":{"channels":[{"alternatives":[{"transcript":"hello"}]}]}}');
        }, 'https://api.deepgram.com/v1/');

        $client = new ListenClient($httpClient);
        $client->request(
            new Deepgram('nova-3', [], [], [new Endpoint(ListenClient::ENDPOINT)]),
            [
                'type' => 'input_audio',
                'input_audio' => ['data' => base64_encode('raw-audio-bytes'), 'format' => 'mp3'],
            ],
        );

        $this->assertSame('raw-audio-bytes', $capturedBody);
    }

    public function testSpeechToTextWithUrlInput()
    {
        $capturedMethod = '';
        $capturedUrl = '';
        $capturedBody = '';
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedBody) {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $body = $options['body'] ?? '';
            $capturedBody = \is_string($body) ? $body : '';

            return new MockResponse('{"results":{"channels":[{"alternatives":[{"transcript":"hi"}]}]}}');
        }, 'https://api.deepgram.com/v1/');

        $client = new ListenClient($httpClient);
        $client->request(
            new Deepgram('nova-3', [], [], [new Endpoint(ListenClient::ENDPOINT)]),
            [
                'type' => 'input_audio',
                'input_audio' => ['url' => 'https://example.com/audio.mp3'],
            ],
        );

        $this->assertSame('POST', $capturedMethod);
        $this->assertSame('/v1/listen', parse_url($capturedUrl, \PHP_URL_PATH));
        $this->assertSame('{"url":"https:\/\/example.com\/audio.mp3"}', $capturedBody);
    }

    public function testSpeechToTextRejectsDataUrlScheme()
    {
        $client = new ListenClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text URL must use "http" or "https" scheme, "data" given.');

        $client->request(
            new Deepgram('nova-3', [], [], [new Endpoint(ListenClient::ENDPOINT)]),
            [
                'type' => 'input_audio',
                'input_audio' => ['url' => 'data:mp3;base64,AAAA'],
            ],
        );
    }

    public function testSpeechToTextRejectsFileScheme()
    {
        $client = new ListenClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text URL must use "http" or "https" scheme, "file" given.');

        $client->request(
            new Deepgram('nova-3', [], [], [new Endpoint(ListenClient::ENDPOINT)]),
            [
                'type' => 'input_audio',
                'input_audio' => ['url' => 'file:///etc/passwd'],
            ],
        );
    }

    public function testSpeechToTextForwardsOptionsAsQueryParams()
    {
        $capturedUrl = '';
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new MockResponse('{}');
        }, 'https://api.deepgram.com/v1/');

        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(__DIR__.'/Fixtures/audio.mp3'));

        $client = new ListenClient($httpClient);
        $client->request(
            new Deepgram('nova-3', [], [], [new Endpoint(ListenClient::ENDPOINT)]),
            $payload,
            ['smart_format' => 'true', 'language' => 'en'],
        );

        $this->assertStringContainsString('smart_format=true', $capturedUrl);
        $this->assertStringContainsString('language=en', $capturedUrl);
        $this->assertStringContainsString('model=nova-3', $capturedUrl);
    }

    public function testRejectsNonHttpRawResult()
    {
        $client = new ListenClient(new MockHttpClient());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported raw result of type');

        $client->convert(new InMemoryRawResult(['content' => 'audio']));
    }

    public function testReturnsTextResultForListen()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'results' => [
                    'channels' => [
                        ['alternatives' => [['transcript' => 'hello world']]],
                    ],
                ],
            ]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $result = (new ListenClient($httpClient))->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('hello world', $result->getContent());
    }

    public function testConcatenatesMultiChannelTranscripts()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'results' => [
                    'channels' => [
                        ['alternatives' => [['transcript' => 'left channel']]],
                        ['alternatives' => [['transcript' => 'right channel']]],
                    ],
                ],
            ]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $result = (new ListenClient($httpClient))->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('left channel right channel', $result->getContent());
    }

    public function testSurfacesDeepgramErrorMessageOnNon200()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(
                ['err_code' => 'INVALID_AUTH', 'err_msg' => 'Invalid credentials.'],
                ['http_code' => 401],
            ),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Deepgram API returned an error: "Invalid credentials.".');

        (new ListenClient($httpClient))->convert(new RawHttpResult($response));
    }

    /**
     * @param array<string, string> $body
     */
    #[DataProvider('provideErrorMessageKeys')]
    public function testSurfacesErrorMessageFromFallbackKeys(array $body, string $expectedMessage)
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($body, ['http_code' => 400]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new ListenClient($httpClient))->convert(new RawHttpResult($response));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function provideErrorMessageKeys(): iterable
    {
        yield 'error key' => [['error' => 'Bad request.'], 'The Deepgram API returned an error: "Bad request.".'];
        yield 'reason key' => [['reason' => 'Quota exceeded.'], 'The Deepgram API returned an error: "Quota exceeded.".'];
        yield 'message key' => [['message' => 'Invalid model.'], 'The Deepgram API returned an error: "Invalid model.".'];
        yield 'no known key' => [['foo' => 'bar'], 'The Deepgram API returned a non-successful status code "400".'];
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

        $response = $httpClient->request('POST', 'listen');

        $this->expectException($expectedException);

        (new ListenClient($httpClient))->convert(new RawHttpResult($response));
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

    public function testFallsBackToGenericExceptionOnUnhandledStatus()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['err_msg' => 'Payment required.'], ['http_code' => 402]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Deepgram API returned an error: "Payment required.".');

        (new ListenClient($httpClient))->convert(new RawHttpResult($response));
    }

    public function testRejectsTranscriptionResponseWithoutChannels()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['request_id' => 'abc-123']),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected Deepgram transcription response: the "results.channels" entry is missing.');

        (new ListenClient($httpClient))->convert(new RawHttpResult($response));
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideDegenerateTranscriptionPayloads')]
    public function testReturnsEmptyTranscriptForDegenerateChannels(array $body)
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($body),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $result = (new ListenClient($httpClient))->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('', $result->getContent());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideDegenerateTranscriptionPayloads(): iterable
    {
        yield 'empty channels' => [['results' => ['channels' => []]]];
        yield 'non-array channel' => [['results' => ['channels' => ['junk']]]];
        yield 'missing alternatives' => [['results' => ['channels' => [['foo' => 'bar']]]]];
        yield 'empty transcript' => [['results' => ['channels' => [['alternatives' => [['transcript' => '']]]]]]];
    }
}
