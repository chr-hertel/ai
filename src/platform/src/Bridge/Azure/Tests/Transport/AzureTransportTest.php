<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Tests\Transport;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Azure\Transport\AzureTransport;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\OpenAi\EmbeddingsClient;
use Symfony\AI\Platform\Bridge\OpenAi\ResponsesClient;
use Symfony\AI\Platform\Bridge\OpenAi\TranscriptionClient;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Task;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AzureTransportTest extends TestCase
{
    public function testItThrowsExceptionWhenBaseUrlIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The base URL must not be empty.');

        new AzureTransport(new MockHttpClient(), '', 'deployment', '2023-12-01', 'api-key');
    }

    public function testItThrowsExceptionWhenDeploymentIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The deployment must not be empty.');

        new AzureTransport(new MockHttpClient(), 'test.openai.azure.com', '', '2023-12-01', 'api-key');
    }

    public function testItThrowsExceptionWhenApiVersionIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API version must not be empty.');

        new AzureTransport(new MockHttpClient(), 'test.openai.azure.com', 'deployment', '', 'api-key');
    }

    public function testItThrowsExceptionWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new AzureTransport(new MockHttpClient(), 'test.openai.azure.com', 'deployment', '2023-12-01', '');
    }

    #[TestWith(['test.openai.azure.com', 'https://test.openai.azure.com/openai/v1/responses'])]
    #[TestWith(['https://test.openai.azure.com', 'https://test.openai.azure.com/openai/v1/responses'])]
    #[TestWith(['https://test.openai.azure.com/', 'https://test.openai.azure.com/openai/v1/responses'])]
    #[TestWith(['http://localhost:8080', 'http://localhost:8080/openai/v1/responses'])]
    public function testItNormalizesTheBaseUrl(string $baseUrl, string $expectedUrl)
    {
        $httpClient = new MockHttpClient([function (string $method, string $url) use ($expectedUrl): MockResponse {
            $this->assertSame($expectedUrl, $url);

            return new MockResponse();
        }]);

        $transport = new AzureTransport($httpClient, $baseUrl, 'gpt-4o', '2023-12-01', 'test-api-key');
        $transport->send('/v1/responses', ['input' => []]);
    }

    public function testTheV1SurfaceNamesTheDeploymentInTheBodyAndOmitsTheApiVersion()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('https://test.openai.azure.com/openai/v1/responses', $url);
            $this->assertStringNotContainsString('api-version', $url);
            $this->assertSame('{"input":[{"role":"user","content":"Hello"}],"model":"my-custom-deployment"}', $options['body']);

            return new MockResponse();
        }]);

        $transport = new AzureTransport($httpClient, 'test.openai.azure.com', 'my-custom-deployment', '2023-12-01', 'test-api-key');
        $transport->send('/v1/responses', ['input' => [['role' => 'user', 'content' => 'Hello']], 'model' => 'gpt-4o']);
    }

    public function testMultipartPayloadsDoNotSendTheContentTypeVerbatim()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertNotSame('Content-Type: multipart/form-data', $options['normalized_headers']['content-type'][0]);
            $this->assertSame('file=audio-data', $options['body']);

            return new MockResponse('{"text": "Hello World"}');
        }]);

        $transport = new AzureTransport($httpClient, 'test.azure.com', 'whspr', '2023-12', 'test-key');
        $transport->send('/v1/audio/transcriptions', ['file' => 'audio-data'], ['Content-Type' => 'multipart/form-data']);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        }]);

        $transport = new AzureTransport($httpClient, 'test.openai.azure.com', 'gpt-4o', '2023-12-01', 'test-api-key');
        $transport->send('/v1/responses', ['input' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }

    public function testItThrowsModelNotFoundExceptionForAnUnknownDeployment()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('The API deployment for this resource does not exist.');

        self::send(new JsonMockResponse(['error' => ['code' => 'DeploymentNotFound', 'message' => 'The API deployment for this resource does not exist.']], ['http_code' => 404]));
    }

    public function testItThrowsBadRequestExceptionOnBadRequest()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid value for "temperature".');

        self::send(new JsonMockResponse(['error' => ['message' => 'Invalid value for "temperature".']], ['http_code' => 400]));
    }

    /**
     * @param array{code?: string, message: string} $error
     */
    #[TestWith([['code' => 'context_length_exceeded', 'message' => 'Context length exceeded for this request.']])]
    #[TestWith([['message' => 'Your input exceeds the context window of this model.']])]
    public function testItThrowsExceedContextSizeExceptionOnContextOverflow(array $error)
    {
        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage($error['message']);

        self::send(new JsonMockResponse(['error' => $error], ['http_code' => 400]));
    }

    public function testItIsExecutingTheCorrectResponsesRequest()
    {
        $httpClient = new MockHttpClient([static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://test.openai.azure.com/openai/v1/responses', $url);
            self::assertSame(['api-key: test-api-key'], $options['normalized_headers']['api-key']);
            self::assertSame('{"model":"gpt-4o","input":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        }]);

        $client = new ResponsesClient(new AzureTransport($httpClient, 'test.openai.azure.com', 'gpt-4o', '2023-12-01', 'test-api-key'), ResponsesModel::class);
        $client->request(new ResponsesModel('gpt-4o'), ['input' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testItHandlesStructuredOutputOption()
    {
        $httpClient = new MockHttpClient([static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('https://test.openai.azure.com/openai/v1/responses', $url);
            self::assertSame('{"temperature":0.7,"text":{"format":{"name":"foo","schema":[],"type":"json_schema"}},"model":"gpt-4o","input":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        }]);

        $options = [
            'temperature' => 0.7,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'foo',
                    'schema' => [],
                ],
            ],
        ];

        $client = new ResponsesClient(new AzureTransport($httpClient, 'test.openai.azure.com', 'gpt-4o', '2023-12-01', 'test-api-key'), ResponsesModel::class);
        $client->request(new ResponsesModel('gpt-4o'), ['input' => [['role' => 'user', 'content' => 'Hello']]], $options);
    }

    public function testItIsExecutingTheCorrectEmbeddingsRequest()
    {
        $httpClient = new MockHttpClient([static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://test.azure.com/openai/deployments/embeddings-deployment/embeddings?api-version=2023-12-01', $url);
            self::assertSame(['api-key: test-api-key'], $options['normalized_headers']['api-key']);
            self::assertSame('{"model":"text-embedding-3-small","input":"Hello, world!"}', $options['body']);

            return new MockResponse();
        }]);

        $client = new EmbeddingsClient(new AzureTransport($httpClient, 'test.azure.com', 'embeddings-deployment', '2023-12-01', 'test-api-key'), EmbeddingsModel::class);
        $client->request(new EmbeddingsModel('text-embedding-3-small'), 'Hello, world!');
    }

    /**
     * @param array<string, string> $options
     */
    #[TestWith([[], 'transcriptions'])]
    #[TestWith([['task' => Task::TRANSCRIPTION], 'transcriptions'])]
    #[TestWith([['task' => Task::TRANSLATION], 'translations'])]
    public function testItRoutesTheWhisperTaskToItsEndpoint(array $options, string $endpoint)
    {
        $httpClient = new MockHttpClient([static function (string $method, string $url) use ($endpoint): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame(\sprintf('https://test.azure.com/openai/deployments/whspr/audio/%s?api-version=2023-12', $endpoint), $url);

            return new MockResponse('{"text": "Hello World"}');
        }]);

        $client = new TranscriptionClient(new AzureTransport($httpClient, 'test.azure.com', 'whspr', '2023-12', 'test-key'), Whisper::class);
        $client->request(new Whisper('whisper-1'), ['file' => 'audio-data'], $options);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    private static function send(MockResponse $response): void
    {
        $transport = new AzureTransport(new MockHttpClient($response), 'test.openai.azure.com', 'gpt-4o', '2023-12-01', 'test-api-key');
        $transport->throwOnError($transport->send('/v1/responses', ['input' => []]));
    }
}
