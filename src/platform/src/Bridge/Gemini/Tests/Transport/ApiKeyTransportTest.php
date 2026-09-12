<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Transport\ApiKeyTransport;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * HTTP-level error translation used to live in the Gemini result converter and
 * moved here together with the transport.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ApiKeyTransportTest extends TestCase
{
    public function testThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $transport = new ApiKeyTransport(new MockHttpClient(new JsonMockResponse([
            'error' => [
                'code' => 400,
                'status' => 'INVALID_ARGUMENT',
                'message' => 'The input token count (1294145) exceeds the maximum number of tokens allowed (1048576).',
            ],
        ], ['http_code' => 400])), 'test-key');

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('exceeds the maximum number of tokens allowed');

        $transport->send(new Model('gemini-2.5-flash'), new RequestEnvelope([], path: '/models/gemini-2.5-flash:generateContent'));
    }

    public function testThrowsServerExceptionOnServerErrorStatus()
    {
        $transport = new ApiKeyTransport(new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 500])), 'test-key');

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        $transport->send(new Model('gemini-2.5-flash'), new RequestEnvelope([], path: '/models/gemini-2.5-flash:generateContent'));
    }
}
