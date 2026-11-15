<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\AbstractUniversalAiClient;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\DocumentParserClient;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\ImageAnalysisClient;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\ImageGenerationClient;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\OcrClient;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\SpeechToTextClient;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\TtsClient;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Every client of the bridge must map an Eden AI error response onto the same platform
 * exception, instead of falling through to a shape check and reporting a missing field.
 *
 * The payloads below were captured from the live API. Eden AI is a FastAPI application and
 * reports errors through "detail", which the shared HttpStatusErrorHandlingTrait cannot
 * read, so each shape is asserted explicitly.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ClientHttpStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{AbstractUniversalAiClient}>
     */
    public static function clientProvider(): iterable
    {
        yield 'ocr' => [new OcrClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key')];
        yield 'documentParser' => [new DocumentParserClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key')];
        yield 'imageAnalysis' => [new ImageAnalysisClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key')];
        yield 'imageGeneration' => [new ImageGenerationClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key')];
        yield 'speechToText' => [new SpeechToTextClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key')];
        yield 'tts' => [new TtsClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key')];
    }

    #[DataProvider('clientProvider')]
    public function testItThrowsAuthenticationExceptionOnInvalidToken(AbstractUniversalAiClient $client)
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid token');

        $client->convert($this->rawResult(['detail' => 'Invalid token'], 401));
    }

    /**
     * A request without credentials is answered with 403, which the shared trait does not
     * map at all.
     */
    #[DataProvider('clientProvider')]
    public function testItThrowsAuthenticationExceptionWhenNotAuthenticated(AbstractUniversalAiClient $client)
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Not authenticated');

        $client->convert($this->rawResult(['detail' => 'Not authenticated'], 403));
    }

    /**
     * The 422 body names the offending field, which is the whole value of the message.
     */
    #[DataProvider('clientProvider')]
    public function testItThrowsBadRequestExceptionWithFieldErrorsOnValidationError(AbstractUniversalAiClient $client)
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Validation error: language: Field required');

        $client->convert($this->rawResult([
            'detail' => 'Validation error',
            'errors' => [['field' => 'language', 'message' => 'Field required']],
        ], 422));
    }

    #[DataProvider('clientProvider')]
    public function testItThrowsBadRequestExceptionOnMalformedModelString(AbstractUniversalAiClient $client)
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid provider format: Expected \'feature/subfeature/provider[/model]\'');

        $client->convert($this->rawResult([
            'detail' => [
                'error' => 'Invalid provider format',
                'message' => 'Expected \'feature/subfeature/provider[/model]\'',
                'received' => 'garbage',
            ],
        ], 400));
    }

    #[DataProvider('clientProvider')]
    public function testItThrowsModelNotFoundExceptionOnUnknownProvider(AbstractUniversalAiClient $client)
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Provider not found: Provider \'nope\' does not support ocr/ocr');

        $client->convert($this->rawResult([
            'detail' => [
                'error' => 'Provider not found',
                'message' => 'Provider \'nope\' does not support ocr/ocr',
            ],
        ], 404));
    }

    #[DataProvider('clientProvider')]
    public function testItThrowsRateLimitExceededExceptionWithRetryAfter(AbstractUniversalAiClient $client)
    {
        try {
            $client->convert($this->rawResult(['detail' => 'Rate limit reached'], 429, ['retry-after' => '42']));
            $this->fail(\sprintf('%s did not throw.', $client::class));
        } catch (RateLimitExceededException $e) {
            $this->assertSame(42, $e->getRetryAfter());
            $this->assertStringContainsString('Rate limit reached', $e->getMessage());
        }
    }

    #[DataProvider('clientProvider')]
    public function testItThrowsServerExceptionOnServerError(AbstractUniversalAiClient $client)
    {
        $this->expectException(ServerException::class);

        $client->convert($this->rawResult(['detail' => 'Internal error'], 503));
    }

    /**
     * A 5xx from a gateway in front of the API carries an HTML body, which must not surface
     * as a JSON decoding exception.
     */
    #[DataProvider('clientProvider')]
    public function testItThrowsServerExceptionOnNonJsonServerError(AbstractUniversalAiClient $client)
    {
        $this->expectException(ServerException::class);

        $httpClient = new MockHttpClient(new MockResponse('<html><body>502 Bad Gateway</body></html>', ['http_code' => 502]));

        $client->convert(new RawHttpResult($httpClient->request('POST', 'https://api.edenai.run/v3/universal-ai')));
    }

    /**
     * The OpenAI-compatible endpoints keep the "error.message" shape, so both must be read.
     */
    #[DataProvider('clientProvider')]
    public function testItReadsOpenAiStyleErrorPayloads(AbstractUniversalAiClient $client)
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Model(s) not found or inactive: openai/nope');

        $client->convert($this->rawResult([
            'error' => ['message' => 'Model(s) not found or inactive: openai/nope', 'type' => 'invalid_request_error'],
        ], 400));
    }

    #[DataProvider('clientProvider')]
    public function testItFallsBackWhenErrorBodyIsEmpty(AbstractUniversalAiClient $client)
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request');

        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 400]));

        $client->convert(new RawHttpResult($httpClient->request('POST', 'https://api.edenai.run/v3/universal-ai')));
    }

    /**
     * FastAPI's default validation shape is what the published OpenAPI schema documents,
     * even though the API emits the "errors" shape instead. Both are parsed.
     */
    #[DataProvider('clientProvider')]
    public function testItReadsTheSchemaDocumentedValidationShape(AbstractUniversalAiClient $client)
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('body.model: field required');

        $client->convert($this->rawResult([
            'detail' => [['loc' => ['body', 'model'], 'msg' => 'field required', 'type' => 'value_error.missing']],
        ], 422));
    }

    /**
     * A status nobody maps must still report the payload rather than a missing field.
     */
    #[DataProvider('clientProvider')]
    public function testItReportsUnmappedStatusCodes(AbstractUniversalAiClient $client)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 402 from Eden AI: "Insufficient credits"');

        $client->convert($this->rawResult(['detail' => 'Insufficient credits'], 402));
    }

    /**
     * The asynchronous endpoint accepts a job with 202, so the speech-to-text client
     * must not treat it as an error the way the synchronous ones would.
     */
    public function testSpeechToTextAcceptsAcceptedStatus()
    {
        $client = new SpeechToTextClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $result = $client->convert($this->rawResult([
            'status' => 'success',
            'output' => ['text' => 'Accepted and finished.'],
        ], 202));

        $this->assertSame('Accepted and finished.', $result->getContent());
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    private function rawResult(array $body, int $statusCode, array $headers = []): RawHttpResult
    {
        $options = ['http_code' => $statusCode];

        if ([] !== $headers) {
            $options['response_headers'] = $headers;
        }

        $httpClient = new MockHttpClient(new JsonMockResponse($body, $options));

        return new RawHttpResult($httpClient->request('POST', 'https://api.edenai.run/v3/universal-ai'));
    }
}
