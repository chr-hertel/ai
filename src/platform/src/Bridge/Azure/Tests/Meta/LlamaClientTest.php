<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Tests\Meta;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Azure\Meta\LlamaClient;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LlamaClientTest extends TestCase
{
    public function testItIsExecutingTheCorrectRequest()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://test.azure.com/chat/completions', $url);
            $this->assertSame('Authorization: test-api-key', $options['normalized_headers']['authorization'][0]);
            $this->assertSame('{"messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        }]);

        $client = new LlamaClient($httpClient, 'test.azure.com', 'test-api-key');
        $client->request(new Llama('llama-3.3-70b-instruct'), ['messages' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        }]);

        $client = new LlamaClient($httpClient, 'test.azure.com', 'test-api-key');
        $client->request(new Llama('llama-3.3-70b-instruct'), ['messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }

    public function testItConvertsTheResponseWithItsFinishReason()
    {
        $client = new LlamaClient(new MockHttpClient(), 'test.azure.com', 'test-api-key');

        $result = $client->convert(new InMemoryRawResult([
            'choices' => [['message' => ['content' => 'Hello!'], 'finish_reason' => 'length']],
        ]));

        $this->assertSame('Hello!', $result->getContent());
        $this->assertTrue($result->getMetadata()->get('finish_reason')->is(FinishReasonCase::LENGTH));
    }
}
