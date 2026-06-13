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
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\TranslationEndpointHandler;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TranslationEndpointHandlerTest extends TestCase
{
    public function testItSupportsOnlyTheTranslationTask()
    {
        $handler = new TranslationEndpointHandler(new MockHttpClient(), 'sk-api-key');

        $this->assertTrue($handler->supports(new Whisper('whisper-1'), Task::TRANSLATION));
        $this->assertFalse($handler->supports(new Whisper('whisper-1')));
        $this->assertFalse($handler->supports(new Whisper('whisper-1'), Task::TRANSCRIPTION));
    }

    public function testItPerformsRequestAgainstTheTranslationEndpoint()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/audio/translations', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse(json_encode(['text' => 'translated text']));
        };

        $handler = new TranslationEndpointHandler(new MockHttpClient([$resultCallback]), 'sk-api-key');
        $result = $handler->request(new Whisper('whisper-1'), ['file' => 'audio-bytes'], ['task' => Task::TRANSLATION]);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('translated text', $result->getContent());
    }
}
