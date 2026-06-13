<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\DallE;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\DallE;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\EndpointHandler;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\ImageResult;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\UrlImage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EndpointHandlerTest extends TestCase
{
    public function testItSupportsTheCorrectModel()
    {
        $handler = new EndpointHandler(new MockHttpClient(), 'sk-api-key');

        $this->assertTrue($handler->supports(new DallE('dall-e-3')));
    }

    public function testItPerformsRequestAndConvertsResult()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/images/generations', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"response_format":"url","model":"dall-e-3","prompt":"a cat"}', $options['body']);

            return new MockResponse(json_encode([
                'data' => [['url' => 'https://example.com/image.jpg']],
            ]));
        };

        $handler = new EndpointHandler(new MockHttpClient([$resultCallback]), 'sk-api-key');
        $result = $handler->request(new DallE('dall-e-3'), 'a cat', ['response_format' => 'url']);

        $this->assertInstanceOf(ImageResult::class, $result);
        $this->assertInstanceOf(UrlImage::class, $result->getContent()[0]);
    }
}
