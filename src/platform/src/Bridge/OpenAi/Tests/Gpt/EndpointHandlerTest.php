<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Gpt;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\EndpointHandler;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
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

        $this->assertTrue($handler->supports(new Gpt('gpt-4o')));
    }

    public function testItThrowsWhenPayloadIsString()
    {
        $handler = new EndpointHandler(new MockHttpClient(), 'sk-api-key');

        $this->expectException(InvalidArgumentException::class);

        $handler->request(new Gpt('gpt-4o'), 'a string payload');
    }

    public function testItPerformsRequestAndConvertsResult()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/responses', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertStringContainsString('"model":"gpt-4o"', $options['body']);

            return new MockResponse(json_encode([
                'output' => [[
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Hello world']],
                ]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15],
            ]));
        };

        $handler = new EndpointHandler(new MockHttpClient([$resultCallback]), 'sk-api-key');
        $result = $handler->request(new Gpt('gpt-4o'), ['messages' => [['role' => 'user', 'content' => 'Hi']]]);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
        $this->assertInstanceOf(TokenUsageInterface::class, $result->getMetadata()->get('token_usage'));
    }
}
