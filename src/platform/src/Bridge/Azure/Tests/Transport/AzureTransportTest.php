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
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Deployment concerns that used to live in the per-contract Azure model clients.
 *
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
        $transport->send(new Model('gpt-4o'), new RequestEnvelope(['input' => []], path: '/v1/responses'));
    }

    public function testItSendsTheApiKeyAsAHeader()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame(['api-key: test-api-key'], $options['normalized_headers']['api-key']);

            return new MockResponse();
        }]);

        $transport = new AzureTransport($httpClient, 'test.openai.azure.com', 'gpt-4o', '2023-12-01', 'test-api-key');
        $transport->send(new Model('gpt-4o'), new RequestEnvelope(['input' => []], path: '/v1/responses'));
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
        $transport->send(
            new Model('gpt-4o'),
            new RequestEnvelope(['input' => [['role' => 'user', 'content' => 'Hello']], 'model' => 'gpt-4o'], path: '/v1/responses'),
        );
    }

    public function testTheClassicSurfaceAddressesTheDeploymentInTheUrlWithAnApiVersion()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://test.azure.com/openai/deployments/embeddings-deployment/embeddings?api-version=2023-12-01', $url);
            $this->assertSame('{"model":"text-embedding-3-small","input":"Hello, world!"}', $options['body']);

            return new MockResponse();
        }]);

        $transport = new AzureTransport($httpClient, 'test.azure.com', 'embeddings-deployment', '2023-12-01', 'test-api-key');
        $transport->send(
            new Model('text-embedding-3-small'),
            new RequestEnvelope(['model' => 'text-embedding-3-small', 'input' => 'Hello, world!'], path: '/v1/embeddings'),
        );
    }

    #[TestWith(['/v1/audio/transcriptions', 'https://test.azure.com/openai/deployments/whspr/audio/transcriptions?api-version=2023-12'])]
    #[TestWith(['/v1/audio/translations', 'https://test.azure.com/openai/deployments/whspr/audio/translations?api-version=2023-12'])]
    public function testItKeepsTheContractsOwnSubPath(string $path, string $expectedUrl)
    {
        $httpClient = new MockHttpClient([function (string $method, string $url) use ($expectedUrl): MockResponse {
            $this->assertSame($expectedUrl, $url);

            return new MockResponse('{"text": "Hello World"}');
        }]);

        $transport = new AzureTransport($httpClient, 'test.azure.com', 'whspr', '2023-12', 'test-key');
        $transport->send(
            new Model('whisper-1'),
            new RequestEnvelope(['file' => 'audio-data'], headers: ['Content-Type' => 'multipart/form-data'], path: $path),
        );
    }

    public function testMultipartPayloadsDoNotSendTheContentTypeVerbatim()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            // A bare "multipart/form-data" without a boundary would make the request
            // unparseable, so the header is dropped and HttpClient sets its own from
            // the array body.
            $this->assertNotSame('Content-Type: multipart/form-data', $options['normalized_headers']['content-type'][0]);
            $this->assertSame('file=audio-data', $options['body']);

            return new MockResponse('{"text": "Hello World"}');
        }]);

        $transport = new AzureTransport($httpClient, 'test.azure.com', 'whspr', '2023-12', 'test-key');
        $transport->send(
            new Model('whisper-1'),
            new RequestEnvelope(['file' => 'audio-data'], headers: ['Content-Type' => 'multipart/form-data'], path: '/v1/audio/transcriptions'),
        );
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
        $transport->send(
            new Model('gpt-4o'),
            new RequestEnvelope(['input' => [['role' => 'user', 'content' => "tool output \xB1 here"]]], path: '/v1/responses'),
        );
    }
}
