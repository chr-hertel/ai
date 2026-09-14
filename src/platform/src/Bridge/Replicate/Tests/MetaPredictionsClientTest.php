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
use Symfony\AI\Platform\Bridge\Replicate\ReplicateJobClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class MetaPredictionsClientTest extends TestCase
{
    public function testSupportsLlamaModel()
    {
        $this->assertTrue(self::createClient()->supports(new Llama('llama-3.1-405b-instruct')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $otherModel = $this->createMock(Model::class);

        $this->assertFalse(self::createClient()->supports($otherModel));
    }

    public function testRequestWithLlamaModel()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "pred-123", "status": "starting"}'));

        $result = (new MetaPredictionsClient(new Client($httpClient, 'test-key')))->request(new Llama('llama-3.1-405b-instruct'), ['prompt' => 'Hello']);

        $this->assertInstanceOf(RawHttpResult::class, $result);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testRequestThrowsExceptionForUnsupportedModel()
    {
        $otherModel = $this->createMock(Model::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model must be an instance of "Symfony\AI\Platform\Bridge\Meta\Llama".');

        self::createClient()->request($otherModel, ['prompt' => 'Hello']);
    }

    public function testRequestThrowsExceptionForStringPayload()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given to "Symfony\AI\Platform\Bridge\Replicate\MetaPredictionsClient".');

        self::createClient()->request(new Llama('llama-3.1-405b-instruct'), 'Hello');
    }

    public function testConvertReturnsAJobHandleForTheCreatedPrediction()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['id' => 'pred-123', 'status' => 'starting']);

        $result = self::createClient()->convert($rawResult);

        $this->assertInstanceOf(JobResult::class, $result);
        $this->assertSame('pred-123', $result->getContent()->getId());
        $this->assertSame('replicate', $result->getContent()->getProvider());
        $this->assertSame(ReplicateJobClient::DEFAULT_MAX_DURATION, $result->getContent()->getMaxDuration());
        $this->assertSame(ReplicateJobClient::DEFAULT_POLL_INTERVAL, $result->getContent()->getPollInterval());
        $this->assertSame('v1/predictions/pred-123', $result->getContent()->get('prediction_path'));
    }

    public function testConvertNamesTheProviderTheJobClientServes()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['id' => 'pred-123']);

        $result = self::createClient('my-replicate')->convert($rawResult);

        $this->assertInstanceOf(JobResult::class, $result);
        $this->assertSame('my-replicate', $result->getContent()->getProvider());
    }

    public function testConvertThrowsExceptionWhenPredictionIdentifierMissing()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['detail' => 'Invalid input']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Replicate API error: "Invalid input".');

        self::createClient()->convert($rawResult);
    }

    public function testConvertThrowsOnHttpError()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"detail": "Invalid version or not permitted"}', ['http_code' => 404]));
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.replicate.com/test'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Replicate API error (HTTP 404)');

        self::createClient()->convert($rawResult);
    }

    private static function createClient(string $name = 'replicate'): MetaPredictionsClient
    {
        return new MetaPredictionsClient(new Client(new MockHttpClient(), 'test-key'), $name);
    }
}
