<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests\Mantle\Transport;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\CredentialProvider;
use AsyncAws\Core\Credentials\Credentials;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Transport\HttpTransport;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author asrar <aszenz@gmail.com>
 */
final class HttpTransportTest extends TestCase
{
    public function testItAuthenticatesWithBearerTokenWhenApiKeyIsProvided()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://bedrock-mantle.us-west-2.api.aws/v1/chat/completions', $url);
            $this->assertSame('Authorization: Bearer bedrock-api-key', $options['normalized_headers']['authorization'][0]);
            $this->assertSame('{"model":"openai.gpt-oss-120b","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };

        $transport = new HttpTransport(new MockHttpClient($responseCallback), 'https://bedrock-mantle.us-west-2.api.aws', 'us-west-2', 'bedrock-api-key');
        $transport->send('/v1/chat/completions', ['model' => 'openai.gpt-oss-120b', 'messages' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testItSignsRequestWithSigV4WhenNoApiKeyIsProvided()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://bedrock-mantle.eu-central-1.api.aws/v1/chat/completions', $url);

            // Header names are case-insensitive and their casing varies across async-aws versions,
            // so assert on the values only (the normalized_headers keys are already lower-cased).
            $authorization = $options['normalized_headers']['authorization'][0];
            $this->assertStringContainsString('AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/', $authorization);
            $this->assertStringContainsString('/eu-central-1/bedrock/aws4_request', $authorization);
            $this->assertStringContainsString('SignedHeaders=', $authorization);
            $this->assertStringContainsString('Signature=', $authorization);
            $this->assertArrayHasKey('x-amz-date', $options['normalized_headers']);
            $this->assertStringEndsWith(': bedrock-mantle.eu-central-1.api.aws', $options['normalized_headers']['host'][0]);

            return new MockResponse();
        };

        $transport = new HttpTransport(
            new MockHttpClient($responseCallback),
            'https://bedrock-mantle.eu-central-1.api.aws',
            'eu-central-1',
            null,
            $this->staticCredentialProvider(),
        );
        $transport->send('/v1/chat/completions', ['model' => 'openai.gpt-oss-120b', 'messages' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testItIncludesSessionTokenHeaderWhenUsingTemporaryCredentials()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            $this->assertArrayHasKey('x-amz-security-token', $options['normalized_headers']);
            $this->assertStringEndsWith(': session-token', $options['normalized_headers']['x-amz-security-token'][0]);

            return new MockResponse();
        };

        $transport = new HttpTransport(
            new MockHttpClient($responseCallback),
            'https://bedrock-mantle.us-west-2.api.aws',
            'us-west-2',
            null,
            $this->staticCredentialProvider('session-token'),
        );
        $transport->send('/v1/chat/completions', ['messages' => []]);
    }

    private function staticCredentialProvider(?string $sessionToken = null): CredentialProvider
    {
        return new class($sessionToken) implements CredentialProvider {
            public function __construct(private readonly ?string $sessionToken)
            {
            }

            public function getCredentials(Configuration $configuration): Credentials
            {
                return new Credentials('AKIDEXAMPLE', 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', $this->sessionToken);
            }
        };
    }
}
