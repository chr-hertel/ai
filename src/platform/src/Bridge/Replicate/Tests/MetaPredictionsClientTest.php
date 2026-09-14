<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Bridge\Replicate\Client;
use Symfony\AI\Platform\Bridge\Replicate\MetaPredictionsClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class MetaPredictionsClientTest extends TestCase
{
    public function testSupportsLlamaModel()
    {
        $httpClient = new MockHttpClient();
        $client = new Client($httpClient, new MockClock(), 'test-key');
        $modelClient = new MetaPredictionsClient($client);

        $this->assertTrue($modelClient->supports(new Llama('llama-3.1-405b-instruct')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $httpClient = new MockHttpClient();
        $client = new Client($httpClient, new MockClock(), 'test-key');
        $modelClient = new MetaPredictionsClient($client);

        $otherModel = $this->createMock(Model::class);
        $this->assertFalse($modelClient->supports($otherModel));
    }

    public function testRequestWithLlamaModel()
    {
        $mockResponse = new MockResponse('{"status": "succeeded"}');
        $httpClient = new MockHttpClient($mockResponse);
        $client = new Client($httpClient, new MockClock(), 'test-key');

        $modelClient = new MetaPredictionsClient($client);
        $result = $modelClient->request(new Llama('llama-3.1-405b-instruct'), ['prompt' => 'Hello']);

        $this->assertInstanceOf(RawHttpResult::class, $result);
    }

    public function testRequestThrowsExceptionForUnsupportedModel()
    {
        $modelClient = new MetaPredictionsClient(new Client(new MockHttpClient(), new MockClock(), 'test-key'));
        $otherModel = $this->createMock(Model::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model must be an instance of "Symfony\AI\Platform\Bridge\Meta\Llama".');

        $modelClient->request($otherModel, ['prompt' => 'Hello']);
    }

    public function testRequestThrowsExceptionForStringPayload()
    {
        $modelClient = new MetaPredictionsClient(new Client(new MockHttpClient(), new MockClock(), 'test-key'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given to "Symfony\AI\Platform\Bridge\Replicate\MetaPredictionsClient".');

        $modelClient->request(new Llama('llama-3.1-405b-instruct'), 'Hello');
    }

    public function testConvertWithSingleOutput()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['output' => ['Hello world']]);

        $converter = self::createConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testConvertWithMultipleOutputs()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['output' => ['Hello', ' ', 'world', '!']]);

        $converter = self::createConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world!', $result->getContent());
    }

    public function testConvertThrowsExceptionWhenOutputMissing()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['status' => 'succeeded']);

        $converter = self::createConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain output.');

        $converter->convert($rawResult);
    }

    public function testConvertWithEmptyOutput()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['output' => []]);

        $converter = self::createConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('', $result->getContent());
    }

    public function testConvertThrowsOnHttpError()
    {
        $mockResponse = new MockResponse('{"detail": "Invalid version or not permitted"}', ['http_code' => 404]);
        $httpClient = new MockHttpClient($mockResponse);
        $response = $httpClient->request('POST', 'https://api.replicate.com/test');
        $rawResult = new RawHttpResult($response);

        $converter = self::createConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Replicate API error (HTTP 404)');

        $converter->convert($rawResult);
    }

    private static function createConverter(): MetaPredictionsClient
    {
        return new MetaPredictionsClient(new Client(new MockHttpClient(), new MockClock(), 'test-key'));
    }
}
