<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\TextToSpeech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech\EndpointHandler;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\BinaryResult;
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

        $this->assertTrue($handler->supports(new TextToSpeech('tts-1')));
    }

    public function testItRequiresVoiceOption()
    {
        $handler = new EndpointHandler(new MockHttpClient(), 'sk-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "voice" option is required for TextToSpeech requests.');

        $handler->request(new TextToSpeech('tts-1'), 'Hello');
    }

    public function testItPerformsRequestAndConvertsResult()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/audio/speech', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"voice":"alloy","model":"tts-1","input":"Hello"}', $options['body']);

            return new MockResponse('binary-audio-content');
        };

        $handler = new EndpointHandler(new MockHttpClient([$resultCallback]), 'sk-api-key');
        $result = $handler->request(new TextToSpeech('tts-1'), 'Hello', ['voice' => 'alloy']);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('binary-audio-content', $result->getContent());
    }
}
