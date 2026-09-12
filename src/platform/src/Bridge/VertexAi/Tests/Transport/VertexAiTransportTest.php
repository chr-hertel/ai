<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Transport\VertexAiTransport;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * Endpoint derivation used to live on the per-contract model clients and moved
 * here together with the transport.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VertexAiTransportTest extends TestCase
{
    public function testItUsesTheRegionalEndpointForARegionalLocation()
    {
        $this->assertUrl(
            'https://europe-west1-aiplatform.googleapis.com/v1/projects/test/locations/europe-west1/publishers/google/models/gemini-2.0-flash:generateContent',
            'europe-west1',
            'test',
        );
    }

    public function testItUsesTheResidencyEndpointForAJurisdictionalLocation()
    {
        $this->assertUrl(
            'https://aiplatform.eu.rep.googleapis.com/v1/projects/test/locations/eu/publishers/google/models/gemini-2.0-flash:generateContent',
            'eu',
            'test',
        );
    }

    public function testItLowercasesTheLocation()
    {
        $this->assertUrl(
            'https://aiplatform.eu.rep.googleapis.com/v1/projects/test/locations/eu/publishers/google/models/gemini-2.0-flash:generateContent',
            'EU',
            'test',
        );
    }

    public function testItUsesTheGlobalEndpointWhenTheProjectIdIsMissing()
    {
        $this->assertUrl(
            'https://aiplatform.googleapis.com/v1/publishers/google/models/gemini-2.0-flash:generateContent',
            'europe-west1',
            null,
        );
    }

    public function testItUsesTheGlobalHostWhenNoLocationIsProvided()
    {
        $this->assertUrl(
            'https://aiplatform.googleapis.com/v1/publishers/google/models/gemini-2.0-flash:generateContent',
            null,
            null,
        );
    }

    public function testItUsesTheGlobalEndpointForTheGlobalLocation()
    {
        $this->assertUrl(
            'https://aiplatform.googleapis.com/v1/projects/test/locations/global/publishers/google/models/gemini-2.0-flash:generateContent',
            'global',
            'test',
        );
    }

    public function testItDerivesTheEndpointForThePredictContractAsWell()
    {
        $this->assertUrl(
            'https://aiplatform.eu.rep.googleapis.com/v1/projects/test/locations/eu/publishers/google/models/text-embedding-005:predict',
            'eu',
            'test',
            'models/text-embedding-005:predict',
        );
    }

    public function testItThrowsOnAnInvalidLocation()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid location "evil.com/europe-west1". Valid options are "global", "eu", "us", or a region like "europe-west1".');

        new VertexAiTransport(new MockHttpClient(), 'evil.com/europe-west1', 'test');
    }

    private function assertUrl(string $expected, ?string $location, ?string $projectId, string $path = 'models/gemini-2.0-flash:generateContent'): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) use ($expected) {
            $this->assertSame($expected, $url);

            return new JsonMockResponse(['candidates' => []]);
        });

        $transport = new VertexAiTransport($httpClient, $location, $projectId);
        $transport->send(new Model('gemini-2.0-flash'), new RequestEnvelope(['contents' => []], path: $path));
    }
}
