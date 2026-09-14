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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Batch\BatchClient;
use Symfony\AI\Platform\Bridge\OpenAi\Batch\JobClient;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\ResponsesClient;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

final class ResponsesClientTest extends TestCase
{
    public function testItWrapsHttpClientInEventSourceHttpClient()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new ResponsesClient(new HttpTransport($httpClient, 'sk-valid-api-key'));

        $this->assertInstanceOf(ResponsesClient::class, $modelClient);
    }

    public function testItAcceptsEventSourceHttpClientDirectly()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $modelClient = new ResponsesClient(new HttpTransport($httpClient, 'sk-valid-api-key'));

        $this->assertInstanceOf(ResponsesClient::class, $modelClient);
    }

    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = self::client();

        $this->assertTrue($modelClient->supports(new Gpt('gpt-4o')));
    }

    public function testStringPayloadThrowsException()
    {
        $modelClient = self::client();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given');

        $modelClient->request(new Gpt('gpt-4o'), 'string payload');
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/responses', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":1,"model":"gpt-4o","messages":[{"role":"user","content":"test message"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new Gpt('gpt-4o'), ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'test message']]], ['temperature' => 1]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            self::assertJson($options['body']);
            self::assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new Gpt('gpt-4o'), ['messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/responses', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":0.7,"text":{"format":{"name":"foo","schema":[],"type":"json"}},"model":"gpt-4o","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };

        $options = [
            'temperature' => 0.7,
            'response_format' => [
                'type' => 'json',
                'json_schema' => [
                    'name' => 'foo',
                    'schema' => []],
            ],
        ];

        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = self::client($httpClient);
        $modelClient->request(new Gpt('gpt-4o'), ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'Hello']]], $options);
    }

    #[TestWith(['EU', 'https://eu.api.openai.com/v1/responses'])]
    #[TestWith(['US', 'https://us.api.openai.com/v1/responses'])]
    #[TestWith([null, 'https://api.openai.com/v1/responses'])]
    public function testItUsesCorrectBaseUrl(?string $region, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ResponsesClient(new HttpTransport($httpClient, 'sk-api-key', $region));
        $modelClient->request(new Gpt('gpt-4o'), ['messages' => []], []);
    }

    public function testThrowsExceedContextSizeExceptionOnContextLengthExceeded()
    {
        $client = self::client(new MockHttpClient(new JsonMockResponse([
            'error' => [
                'message' => "This model's maximum context length is 128000 tokens. However, your messages resulted in 145575 tokens.",
                'type' => 'invalid_request_error',
                'param' => 'messages',
                'code' => 'context_length_exceeded',
            ],
        ], ['http_code' => 400])));

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('maximum context length is 128000 tokens');

        $client->convert($client->request(new Gpt('gpt-4o'), ['input' => []]));
    }

    public function testStreamYieldsToolCallComplete()
    {
        $httpResponse = $this->createStub(HttpResponse::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'response.completed',
                'response' => [
                    'output' => [
                        [
                            'type' => 'function_call',
                            'id' => 'call_123',
                            'name' => 'get_weather',
                            'arguments' => '{"city":"Berlin"}',
                        ],
                    ],
                ],
            ],
        ];

        $streamResult = self::client()->convert(new InMemoryRawResult([], $events, $httpResponse), ['stream' => true]);
        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $this->assertInstanceOf(MetadataDelta::class, $chunks[1]);
        $this->assertTrue($chunks[1]->getValue()->is(FinishReasonCase::TOOL_CALL));
        $this->assertSame('call_123', $chunks[0]->getToolCalls()[0]->getId());
        $this->assertSame('get_weather', $chunks[0]->getToolCalls()[0]->getName());
        $this->assertSame(['city' => 'Berlin'], $chunks[0]->getToolCalls()[0]->getArguments());
    }

    public function testStreamYieldsToolCallCompleteFromOutputItemDone()
    {
        $httpResponse = $this->createStub(HttpResponse::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'response.output_item.done',
                'item' => [
                    'type' => 'function_call',
                    'id' => 'call_123',
                    'name' => 'get_weather',
                    'arguments' => '{"city":"Berlin"}',
                ],
            ],
            [
                'type' => 'response.completed',
                'response' => [
                    'output' => [],
                ],
            ],
        ];

        $streamResult = self::client()->convert(new InMemoryRawResult([], $events, $httpResponse), ['stream' => true]);
        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $this->assertInstanceOf(MetadataDelta::class, $chunks[1]);
        $this->assertTrue($chunks[1]->getValue()->is(FinishReasonCase::TOOL_CALL));
        $this->assertSame('call_123', $chunks[0]->getToolCalls()[0]->getId());
        $this->assertSame('get_weather', $chunks[0]->getToolCalls()[0]->getName());
        $this->assertSame(['city' => 'Berlin'], $chunks[0]->getToolCalls()[0]->getArguments());
    }

    public function testItSubmitsABatchOfInputsInsteadOfOneRequest()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = [$url, self::collectBody($options)];

            return str_contains($url, '/v1/files')
                ? new MockResponse('{"id": "file-abc"}')
                : new MockResponse('{"id": "batch_123", "status": "validating"}');
        });

        $result = self::batchingClient($httpClient)->request(new Gpt('gpt-4o-mini'), [
            'first' => ['input' => [['role' => 'user', 'content' => 'What is the capital of France?']]],
            'second' => ['input' => [['role' => 'user', 'content' => 'What is the capital of Germany?']]],
        ], ['batch' => true, 'max_output_tokens' => 50]);

        $this->assertSame('batch_123', $result->getData()['id']);
        $this->assertSame('https://api.openai.com/v1/files', $recorded[0][0]);
        $this->assertSame('https://api.openai.com/v1/batches', $recorded[1][0]);

        // Each line is the request it would have been on its own, without the "batch" option itself.
        $this->assertStringContainsString('{"custom_id":"first","method":"POST","url":"\/v1\/responses","body":{"max_output_tokens":50,"model":"gpt-4o-mini","input":[{"role":"user","content":"What is the capital of France?"}]}}', $recorded[0][1]);
        $this->assertStringNotContainsString('"batch"', $recorded[0][1]);
    }

    public function testItRefusesABatchOnADeploymentWithoutBatches()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This deployment of the Responses API does not support batches.');

        self::client()->request(new Gpt('gpt-4o-mini'), ['first' => ['input' => []]], ['batch' => true]);
    }

    public function testItRefusesToStreamABatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch is answered as a file hours later, so it cannot be streamed.');

        self::batchingClient()->request(new Gpt('gpt-4o-mini'), ['first' => ['input' => []]], ['batch' => true, 'stream' => true]);
    }

    public function testItRefusesASingleInputAsABatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch invocation expects an array of inputs, keyed by the identifier to report each result under, and not a single input.');

        self::batchingClient()->request(new Gpt('gpt-4o-mini'), ['input' => [['role' => 'user', 'content' => 'Hello']]], ['batch' => true]);
    }

    public function testItSaysWhatIsWrongWithAnInputThatIsNotARequest()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The input "first" of the batch did not normalize into a request');

        self::batchingClient()->request(new Gpt('gpt-4o-mini'), ['first' => 'What is the capital of France?'], ['batch' => true]);
    }

    public function testItRefusesAnEmptyBatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch invocation expects a non-empty array of inputs, "array" given.');

        self::batchingClient()->request(new Gpt('gpt-4o-mini'), [], ['batch' => true]);
    }

    public function testItConvertsABatchSubmissionIntoAJob()
    {
        $httpResponse = $this->createStub(HttpResponse::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn([
            'id' => 'batch_123',
            'object' => 'batch',
            'endpoint' => '/v1/responses',
            'completion_window' => '24h',
            'status' => 'validating',
        ]);

        $result = self::client()->convert(new RawHttpResult($httpResponse), ['batch' => true]);

        $this->assertInstanceOf(JobResult::class, $result);

        $handle = $result->getContent();

        $this->assertSame('batch_123', $handle->getId());
        $this->assertSame('openai', $handle->getProvider());
        $this->assertSame(JobClient::KIND, $handle->get('kind'));
        $this->assertSame('/v1/responses', $handle->get('endpoint'));
        // The completion window is the longest the batch may take, so a caller need not know it.
        $this->assertSame(86400, $handle->getMaxDuration());
        // And a job running for hours is not worth asking about every second.
        $this->assertSame(JobClient::DEFAULT_POLL_INTERVAL, $handle->getPollInterval());
    }

    #[TestWith(['12h', 43200])]
    #[TestWith(['24h', 86400])]
    #[TestWith([null, 86400])]
    #[TestWith(['whenever', 86400])]
    public function testItCarriesTheCompletionWindowOnTheHandle(?string $window, int $expected)
    {
        $httpResponse = $this->createStub(HttpResponse::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn(['id' => 'batch_123', 'completion_window' => $window]);

        $result = self::client()->convert(new RawHttpResult($httpResponse), ['batch' => true]);

        $this->assertInstanceOf(JobResult::class, $result);
        $this->assertSame($expected, $result->getContent()->getMaxDuration());
    }

    public function testItFailsWhenABatchSubmissionCarriesNoIdentifier()
    {
        $httpResponse = $this->createStub(HttpResponse::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn(['object' => 'batch']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The OpenAI response does not contain a batch identifier.');

        self::client()->convert(new RawHttpResult($httpResponse), ['batch' => true]);
    }

    public function testItReportsAnErrorOnABatchSubmission()
    {
        $httpResponse = (new MockHttpClient(new JsonMockResponse(['error' => ['message' => 'Incorrect API key provided.']], ['http_code' => 401])))->request('POST', 'https://api.openai.com/v1/batches');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Incorrect API key provided.');

        self::client()->convert(new RawHttpResult($httpResponse), ['batch' => true]);
    }

    private static function batchingClient(MockHttpClient $httpClient = new MockHttpClient()): ResponsesClient
    {
        return new ResponsesClient(
            new HttpTransport($httpClient, 'sk-api-key'),
            batchClient: new BatchClient($httpClient, 'sk-api-key', 'https://api.openai.com', ResponsesClient::PATH),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function collectBody(array $options): string
    {
        $body = $options['body'] ?? '';

        if (!\is_callable($body)) {
            return (string) $body;
        }

        $collected = '';

        while ('' !== $chunk = $body(8192)) {
            $collected .= $chunk;
        }

        return $collected;
    }

    private static function client(MockHttpClient $httpClient = new MockHttpClient()): ResponsesClient
    {
        return new ResponsesClient(new HttpTransport($httpClient, 'sk-api-key'));
    }
}
