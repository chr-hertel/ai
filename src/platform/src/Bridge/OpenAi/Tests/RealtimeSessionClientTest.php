<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Realtime;
use Symfony\AI\Platform\Bridge\OpenAi\RealtimeSessionClient;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RealtimeSessionResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Saiful Islam Feroz <saiful.feroz@gmail.com>
 */
final class RealtimeSessionClientTest extends TestCase
{
    public function testSupportsRealtimeModel()
    {
        $this->assertTrue(self::client()->supports(new Realtime('gpt-4o-realtime-preview')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $this->assertFalse(self::client()->supports(new Model('other-model')));
    }

    public function testRealtimeSessionRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/realtime/client_secrets', $url);
            self::assertSame('Authorization: Bearer sk-test-key', $options['normalized_headers']['authorization'][0]);

            $body = json_decode($options['body'], true);
            $session = $body['session'] ?? [];

            self::assertSame('realtime', $session['type']);
            self::assertSame('gpt-4o-realtime-preview', $session['model']);
            self::assertSame('You are a helpful customer service voice assistant.', $session['instructions']);
            self::assertSame('alloy', $session['audio']['output']['voice']);
            self::assertSame(['text', 'audio'], $session['output_modalities']);
            self::assertArrayNotHasKey('modalities', $session);

            return new MockResponse('{}');
        };

        self::client(new MockHttpClient([$resultCallback]))->request(
            new Realtime('gpt-4o-realtime-preview'),
            'You are a helpful customer service voice assistant.',
            ['voice' => 'alloy'],
        );
    }

    public function testRealtimeSessionRequestMapsModalitiesToOutputModalities()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            $body = json_decode($options['body'], true);
            $session = $body['session'] ?? [];

            self::assertSame(['text'], $session['output_modalities']);
            self::assertArrayNotHasKey('modalities', $session);

            return new MockResponse('{}');
        };

        self::client(new MockHttpClient([$resultCallback]))->request(
            new Realtime('gpt-4o-realtime-preview'),
            'Instructions',
            ['modalities' => ['text']],
        );
    }

    public function testThrowsOnAuthenticationError()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthorized');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getContent')->willReturn('{"error":{"message":"Unauthorized"}}');

        self::client()->convert(new RawHttpResult($response));
    }

    public function testThrowsOnUnexpectedStatusCode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The OpenAI Realtime API returned an error: "Unexpected"');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(418);
        $response->method('getContent')->willReturn('Unexpected');

        self::client()->convert(new RawHttpResult($response));
    }

    public function testConvertsRealtimeSessionResponse()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'object' => 'realtime.client_secret',
            'value' => 'ek_secret_token_abc',
            'expires_at' => 1795000000,
            'session' => [
                'id' => 'sess_test_999',
                'type' => 'realtime',
                'model' => 'gpt-4o-realtime-preview',
                'output_modalities' => ['text', 'audio'],
                'instructions' => 'Speak clearly',
                'audio' => [
                    'output' => [
                        'voice' => 'alloy',
                    ],
                ],
            ],
        ]);

        $result = self::client()->convert(new RawHttpResult($response));

        $this->assertInstanceOf(RealtimeSessionResult::class, $result);
        $this->assertSame('sess_test_999', $result->getId());
        $this->assertSame('ek_secret_token_abc', $result->getClientSecret());
        $this->assertSame(1795000000, $result->getExpiresAt());
        $this->assertSame('gpt-4o-realtime-preview', $result->getModel());
        $this->assertSame('alloy', $result->getVoice());
        $this->assertSame(['text', 'audio'], $result->getModalities());
        $this->assertSame([
            'id' => 'sess_test_999',
            'client_secret' => 'ek_secret_token_abc',
            'expires_at' => 1795000000,
            'model' => 'gpt-4o-realtime-preview',
            'voice' => 'alloy',
            'modalities' => ['text', 'audio'],
        ], $result->getContent());
    }

    public function testConvertsRealtimeSessionResponseWithCustomOutputModalities()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'value' => 'ek_secret_custom',
            'expires_at' => 1795000000,
            'session' => [
                'id' => 'sess_custom_123',
                'model' => 'gpt-4o-realtime-preview',
                'output_modalities' => ['text'],
            ],
        ]);

        $result = self::client()->convert(new RawHttpResult($response));

        $this->assertInstanceOf(RealtimeSessionResult::class, $result);
        $this->assertSame('sess_custom_123', $result->getId());
        $this->assertSame(['text'], $result->getModalities());
    }

    private static function client(?HttpClientInterface $httpClient = null): RealtimeSessionClient
    {
        return new RealtimeSessionClient(new HttpTransport($httpClient ?? new MockHttpClient(), 'sk-test-key'));
    }
}
