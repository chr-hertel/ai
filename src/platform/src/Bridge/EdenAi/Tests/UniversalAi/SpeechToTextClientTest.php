<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\UniversalAi;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\EdenAiJobClient;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\SpeechToTextClient;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class SpeechToTextClientTest extends TestCase
{
    public function testItSupportsSpeechToTextModelOnly()
    {
        $client = new SpeechToTextClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $this->assertTrue($client->supports(new SpeechToText('audio/speech_to_text_async/openai')));
        $this->assertFalse($client->supports(new Ocr('ocr/ocr/google')));
    }

    public function testItSubmitsToTheAsynchronousEndpoint()
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = [$method, $url];
            $body = json_decode($options['body'], true);
            $this->assertSame('audio/speech_to_text_async/openai', $body['model']);
            $this->assertSame('https://example.com/audio.mp3', $body['input']['file']);
            $this->assertSame('en', $body['input']['language']);

            return new JsonMockResponse(['status' => 'success', 'output' => ['text' => 'Hello world']]);
        });

        $client = new SpeechToTextClient($httpClient, 'https://api.edenai.run', 'test-key');
        $result = $client->request(new SpeechToText('audio/speech_to_text_async/openai'), 'https://example.com/audio.mp3', ['language' => 'en']);

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertSame(['POST', 'https://api.edenai.run/v3/universal-ai/async'], $requests[0]);
        $this->assertSame('Hello world', $result->getData()['output']['text']);
    }

    /**
     * Submitting is a single request: a job that is still running is handed to the caller as a
     * handle when converting, it is not waited for here.
     */
    public function testItDoesNotPollARunningJob()
    {
        $httpClient = new MockHttpClient(static fn () => new JsonMockResponse(
            ['status' => 'processing', 'public_id' => 'job-123'],
            ['http_code' => 202],
        ));

        $client = new SpeechToTextClient($httpClient, 'https://api.edenai.run', 'test-key');
        $result = $client->request(new SpeechToText('audio/speech_to_text_async/google'), 'https://example.com/audio.mp3');

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertSame('processing', $result->getData()['status']);
    }

    public function testItKeepsWebhookOptionsAtRootLevel()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $body = json_decode($options['body'], true);
            $this->assertSame('https://example.com/webhook', $body['webhook_receiver']);
            $this->assertSame(['job' => 'transcription'], $body['user_webhook_parameters']);
            $this->assertArrayNotHasKey('webhook_receiver', $body['input']);
            $this->assertArrayNotHasKey('user_webhook_parameters', $body['input']);

            return new JsonMockResponse(['status' => 'processing', 'public_id' => 'job-123'], ['http_code' => 202]);
        });

        $client = new SpeechToTextClient($httpClient, 'https://api.edenai.run', 'test-key');
        $client->request(new SpeechToText('audio/speech_to_text_async/openai'), 'https://example.com/audio.mp3', [
            'webhook_receiver' => 'https://example.com/webhook',
            'user_webhook_parameters' => ['job' => 'transcription'],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUploadsBinaryPayloadBeforeSubmittingTheJob()
    {
        $responses = [
            new JsonMockResponse(['file_id' => 'file-42']),
            new JsonMockResponse(['status' => 'success', 'output' => ['text' => '']]),
        ];

        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$responses) {
            $requests[] = [$method, $url, $options['body'] ?? null];

            return array_shift($responses);
        });

        $client = new SpeechToTextClient($httpClient, 'https://api.edenai.run', 'test-key');
        $client->request(new SpeechToText('audio/speech_to_text_async/openai'), [
            'file_data' => [
                'data' => base64_encode('binary-audio'),
                'filename' => 'audio.mp3',
                'format' => 'audio/mpeg',
            ],
        ]);

        $this->assertSame(2, $httpClient->getRequestsCount());
        $this->assertSame('https://api.edenai.run/v3/upload', $requests[0][1]);

        $body = json_decode(\is_string($requests[1][2]) ? $requests[1][2] : '', true);
        $this->assertSame('file-42', $body['input']['file']);
        $this->assertArrayNotHasKey('file_data', $body['input']);
    }

    public function testItConvertsResponseToTextResult()
    {
        $client = new SpeechToTextClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');
        $httpResponse = $this->createResponse([
            'status' => 'success',
            'provider' => 'deepgram',
            'cost' => '0.0008',
            'output' => [
                'text' => 'Hello world',
                'diarization' => [
                    'total_speakers' => 1,
                    'entries' => [
                        ['segment' => 'Hello world', 'speaker' => 1],
                    ],
                ],
            ],
        ]);

        $result = $client->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
        $this->assertSame('deepgram', $result->getMetadata()->get('provider'));
        $this->assertSame(0.0008, $result->getMetadata()->get('cost'));
        $this->assertSame(1, $result->getMetadata()->get('diarization')['total_speakers']);
    }

    public function testItThrowsWhenJobFails()
    {
        $client = new SpeechToTextClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');
        $httpResponse = $this->createResponse([
            'status' => 'fail',
            'output' => null,
            'error' => ['message' => 'Audio could not be processed.'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI request failed: "Audio could not be processed."');

        $client->convert(new RawHttpResult($httpResponse));
    }

    public function testItThrowsWhenTextIsMissing()
    {
        $client = new SpeechToTextClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');
        $httpResponse = $this->createResponse(['status' => 'success', 'output' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain text.');

        $client->convert(new RawHttpResult($httpResponse));
    }

    /**
     * Providers that need longer than the request answer with a job still running, which the
     * caller resolves later through the job client instead of waiting for it inside invoke().
     */
    public function testItHandsOutAJobWhileTheTranscriptionIsStillRunning()
    {
        $client = new SpeechToTextClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key', new EdenAiJobClient(new MockHttpClient(), 'test-key', 'https://api.edenai.run', 'edenai'));
        $httpResponse = $this->createResponse([
            'status' => 'processing',
            'public_id' => '822436ad-3730-4149-a2c5-c7bdc4e3d9a9',
            'output' => null,
        ], 202);

        $result = $client->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(JobResult::class, $result);

        $handle = $result->getContent();
        $this->assertSame('822436ad-3730-4149-a2c5-c7bdc4e3d9a9', $handle->getId());
        $this->assertSame('edenai', $handle->getProvider());
        $this->assertSame('speech_to_text_async', $handle->get('subfeature'));
        $this->assertSame(600, $handle->getMaxDuration());
    }

    public function testItThrowsWhenAnAcceptedJobCannotBeResolved()
    {
        $client = new SpeechToTextClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');
        $httpResponse = $this->createResponse(['status' => 'processing'], 202);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI accepted the job but did not return a public_id to resolve it with.');

        $client->convert(new RawHttpResult($httpResponse));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createResponse(array $data, int $statusCode = 200): ResponseInterface
    {
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn($statusCode);
        $httpResponse->method('toArray')->willReturn($data);

        return $httpResponse;
    }
}
