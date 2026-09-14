<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\MessagesClient;
use Symfony\AI\Platform\Bridge\Anthropic\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpTransportTest extends TestCase
{
    public function testRateLimitCarriesErrorMessageAndRetryAfter()
    {
        $transport = new HttpTransport(new MockHttpClient([
            new MockResponse('{"type":"error","error":{"type":"rate_limit_error","message":"This request would exceed the rate limit for your organization"}}', [
                'http_code' => 429,
                'response_headers' => ['retry-after' => '60'],
            ]),
        ]), 'test-api-key');

        try {
            $transport->throwOnError($transport->send(new Claude('claude-sonnet-4-5'), '/v1/messages', ['messages' => []]));
            $this->fail('Expected a RateLimitExceededException to be thrown.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame(60, $e->getRetryAfter());
            $this->assertStringContainsString('This request would exceed the rate limit for your organization', $e->getMessage());
        }
    }

    public function testUnhandledErrorStatusWithoutStreamingIsMappedFromTheErrorBody()
    {
        $client = new MessagesClient(new HttpTransport(new MockHttpClient([
            new MockResponse('{"type":"error","error":{"type":"permission_error","message":"Your API key does not have permission to use the specified resource."}}', [
                'http_code' => 403,
            ]),
        ]), 'test-api-key'));

        $raw = $client->request(new Claude('claude-sonnet-4-5'), ['messages' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API Error [permission_error]: "Your API key does not have permission to use the specified resource."');

        $client->convert($raw);
    }
}
