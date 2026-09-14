<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\GenerateContentClient;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Bridge\VertexAi\Transport\VertexAiTransport;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class GenerateContentClientTest extends TestCase
{
    public function testItInvokesTheTextModelsSuccessfully()
    {
        $payload = [
            'content' => [
                ['parts' => ['text' => 'Hello, world!']],
            ],
        ];
        $expectedResponse = [
            'candidates' => [$payload],
        ];
        $httpClient = new MockHttpClient(
            new JsonMockResponse($expectedResponse),
        );

        $client = self::client($httpClient, 'global', 'test');

        $result = $client->request(new Model('gemini-2.0-flash'), $payload);
        $this->assertInstanceOf(RawHttpResult::class, $result);
        $data = $result->getData();
        $info = $result->getObject()->getInfo();

        $this->assertNotEmpty($data);
        $this->assertNotEmpty($info);
        $this->assertSame('POST', $info['http_method']);
        $this->assertSame(
            'https://aiplatform.googleapis.com/v1/projects/test/locations/global/publishers/google/models/gemini-2.0-flash:generateContent',
            $info['url'],
        );
        $this->assertSame($expectedResponse, $data);
    }

    public function testItUsesTheRegionalEndpointForARegionalLocation()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame(
                'https://europe-west1-aiplatform.googleapis.com/v1/projects/test/locations/europe-west1/publishers/google/models/gemini-2.0-flash:generateContent',
                $url,
            );

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = self::client($httpClient, 'europe-west1', 'test');
        $client->request(new Model('gemini-2.0-flash'), ['contents' => []]);
    }

    public function testItUsesTheResidencyEndpointForAJurisdictionalLocation()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame(
                'https://aiplatform.eu.rep.googleapis.com/v1/projects/test/locations/eu/publishers/google/models/gemini-2.0-flash:generateContent',
                $url,
            );

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = self::client($httpClient, 'eu', 'test');
        $client->request(new Model('gemini-2.0-flash'), ['contents' => []]);
    }

    public function testItUsesTheGlobalHostWhenNoLocationIsProvided()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertStringStartsWith(
                'https://aiplatform.googleapis.com/v1/publishers/google/models/gemini-2.0-flash:generateContent',
                $url,
            );

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = self::client($httpClient, apiKey: 'test-key');
        $client->request(new Model('gemini-2.0-flash'), ['contents' => []]);
    }

    public function testItUsesTheGlobalEndpointWhenTheProjectIdIsMissing()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame(
                'https://aiplatform.googleapis.com/v1/publishers/google/models/gemini-2.0-flash:generateContent',
                $url,
            );

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = self::client($httpClient, 'europe-west1');
        $client->request(new Model('gemini-2.0-flash'), ['contents' => []]);
    }

    public function testItLowercasesTheLocation()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame(
                'https://aiplatform.eu.rep.googleapis.com/v1/projects/test/locations/eu/publishers/google/models/gemini-2.0-flash:generateContent',
                $url,
            );

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = self::client($httpClient, 'EU', 'test');
        $client->request(new Model('gemini-2.0-flash'), ['contents' => []]);
    }

    public function testItThrowsOnAnInvalidLocation()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid location "evil.com/europe-west1". Valid options are "global", "eu", "us", or a region like "europe-west1".');

        self::client(new MockHttpClient(), 'evil.com/europe-west1', 'test');
    }

    public function testRequestWithStreamAsksForSseFormat()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertStringContainsString(':streamGenerateContent', $url);
            $this->assertStringContainsString('alt=sse', $url);
            $this->assertStringContainsString('key=test-key', $url);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = self::client($httpClient, 'global', 'test', 'test-key');
        $client->request(new Model('gemini-2.0-flash'), ['contents' => []], ['stream' => true]);
    }

    public function testItPassesServerToolsFromOptions()
    {
        $payload = [
            'content' => [
                ['parts' => ['text' => 'Server tool test']],
            ],
        ];
        $httpClient = new MockHttpClient(
            function ($method, $url, $options) {
                $this->assertJsonStringEqualsJsonString(
                    <<<'JSON'
                        {
                          "tools": [
                            {"google_search": {}}
                          ],
                          "content": [
                            {"parts":{"text":"Server tool test"}}
                          ]
                        }
                        JSON,
                    $options['body'],
                );

                return new JsonMockResponse('{}');
            }
        );

        $client = self::client($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), $payload, ['server_tools' => ['google_search' => true]]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = self::client($httpClient, 'global', 'test');
        $client->request(new Model('gemini-2.0-flash'), ['contents' => [['parts' => [['text' => "tool output \xB1 here"]]]]]);
    }

    public function testItPutsTheStructuredOutputSchemaUnderResponseSchema()
    {
        $schema = ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']]];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($schema) {
            $body = json_decode($options['body'], true);

            $this->assertSame(['responseMimeType' => 'application/json', 'responseSchema' => $schema], $body['generationConfig']);

            return new JsonMockResponse(['candidates' => []]);
        });

        self::client($httpClient, 'global', 'test')->request(new Model('gemini-2.0-flash'), ['contents' => []], [
            PlatformSubscriber::RESPONSE_FORMAT => ['json_schema' => ['schema' => $schema]],
        ]);
    }

    public function testItKeepsTheRequestShapedOptionsOfTheVertexAiApi()
    {
        $safetySettings = [['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_LOW_AND_ABOVE']];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($safetySettings) {
            $body = json_decode($options['body'], true);

            $this->assertSame(['temperature' => 0.2], $body['generationConfig']);
            $this->assertSame($safetySettings, $body['safetySettings']);
            $this->assertSame(['team' => 'ai'], $body['labels']);

            return new JsonMockResponse(['candidates' => []]);
        });

        self::client($httpClient, 'global', 'test')->request(new Model('gemini-2.0-flash'), ['contents' => []], [
            'generationConfig' => ['temperature' => 0.2],
            'safetySettings' => $safetySettings,
            'labels' => ['team' => 'ai'],
        ]);
    }

    public function testItWrapsAStringPayloadIntoUserContents()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertJsonStringEqualsJsonString(
                '{"contents":[{"role":"user","parts":[{"text":"Hello, world!"}]}]}',
                $options['body'],
            );

            return new JsonMockResponse(['candidates' => []]);
        });

        self::client($httpClient, 'global', 'test')->request(new Model('gemini-2.0-flash'), 'Hello, world!');
    }

    private static function client(MockHttpClient $httpClient, ?string $location = null, ?string $projectId = null, ?string $apiKey = null): GenerateContentClient
    {
        return new GenerateContentClient(
            new VertexAiTransport($httpClient, $location, $projectId, $apiKey),
            GenerateContentClient::RESPONSE_SCHEMA_KEY_VERTEX_AI,
            Model::class,
        );
    }
}
