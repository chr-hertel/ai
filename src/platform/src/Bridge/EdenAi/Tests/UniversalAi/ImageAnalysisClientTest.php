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
use Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis;
use Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis\Result\ImageAnalysisResult;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\ImageAnalysisClient;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ImageAnalysisClientTest extends TestCase
{
    public function testItSupportsImageAnalysisModelOnly()
    {
        $client = new ImageAnalysisClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $this->assertTrue($client->supports(new ImageAnalysis('image/object_detection/google')));
        $this->assertFalse($client->supports(new Ocr('ocr/ocr/google')));
    }

    public function testItConvertsObjectDetectionResponse()
    {
        $client = new ImageAnalysisClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');
        $httpResponse = $this->createResponse([
            'status' => 'success',
            'provider' => 'google',
            'cost' => '0.00225',
            'output' => [
                'items' => [
                    ['label' => 'Person', 'confidence' => 0.92, 'x_min' => 0.16, 'x_max' => 0.57, 'y_min' => 0.11, 'y_max' => 1.0],
                    ['label' => 'Accordion', 'confidence' => 0.90, 'x_min' => 0.24, 'x_max' => 0.56, 'y_min' => 0.31, 'y_max' => 0.86],
                ],
            ],
        ]);

        $result = $client->convert(new RawHttpResult($httpResponse));
        $analysis = $result->getContent();

        $this->assertInstanceOf(ImageAnalysisResult::class, $analysis);
        $this->assertCount(2, $analysis->getItems());
        $this->assertSame('Person', $analysis->getItems()[0]['label']);
        $this->assertSame('google', $analysis->getProvider());
        $this->assertSame(0.00225, $analysis->getCost());
    }

    public function testItConvertsExplicitContentResponse()
    {
        $client = new ImageAnalysisClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');
        $httpResponse = $this->createResponse([
            'status' => 'success',
            'provider' => 'google',
            'output' => [
                'nsfw_likelihood' => 2,
                'nsfw_likelihood_score' => 0.4,
                'items' => [
                    ['label' => 'Adult', 'likelihood' => 1, 'likelihood_score' => 0.2, 'category' => 'Sexual'],
                ],
            ],
        ]);

        $result = $client->convert(new RawHttpResult($httpResponse));
        $analysis = $result->getContent();

        $this->assertInstanceOf(ImageAnalysisResult::class, $analysis);
        $this->assertSame(2, $analysis->getOutput()['nsfw_likelihood']);
        $this->assertCount(1, $analysis->getItems());
    }

    public function testItThrowsWhenProviderFails()
    {
        $client = new ImageAnalysisClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');
        $httpResponse = $this->createResponse([
            'status' => 'fail',
            'output' => null,
            'error' => ['message' => 'Unsupported image format.'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI request failed: "Unsupported image format."');

        $client->convert(new RawHttpResult($httpResponse));
    }

    public function testItThrowsWhenOutputIsMissing()
    {
        $client = new ImageAnalysisClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');
        $httpResponse = $this->createResponse(['status' => 'success']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain output.');

        $client->convert(new RawHttpResult($httpResponse));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createResponse(array $data): ResponseInterface
    {
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn($data);

        return $httpResponse;
    }
}
