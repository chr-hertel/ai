<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Whisper;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Task;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\TranscriptionEndpointHandler;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TranscriptionEndpointHandlerTest extends TestCase
{
    public function testItSupportsTheTranscriptionTask()
    {
        $handler = new TranscriptionEndpointHandler(new MockHttpClient(), 'sk-api-key');

        $this->assertTrue($handler->supports(new Whisper('whisper-1')));
        $this->assertTrue($handler->supports(new Whisper('whisper-1'), Task::TRANSCRIPTION));
        $this->assertFalse($handler->supports(new Whisper('whisper-1'), Task::TRANSLATION));
    }

    public function testItPerformsRequestAgainstTheTranscriptionEndpoint()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/audio/transcriptions', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse(json_encode(['text' => 'transcribed text']));
        };

        $handler = new TranscriptionEndpointHandler(new MockHttpClient([$resultCallback]), 'sk-api-key');
        $result = $handler->request(new Whisper('whisper-1'), ['file' => 'audio-bytes']);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('transcribed text', $result->getContent());
    }
}
