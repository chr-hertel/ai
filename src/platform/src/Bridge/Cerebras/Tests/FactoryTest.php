<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cerebras\Factory;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
class FactoryTest extends TestCase
{
    public function testItDoesNotAllowAnEmptyKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        Factory::createPlatform('');
    }

    #[TestWith(['api-key-without-prefix'])]
    #[TestWith(['pk-api-key'])]
    #[TestWith(['SK-api-key'])]
    #[TestWith(['skapikey'])]
    #[TestWith(['sk api-key'])]
    #[TestWith(['sk'])]
    public function testItVerifiesIfTheKeyStartsWithCsk(string $invalidApiKey)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must start with "csk-".');

        Factory::createPlatform($invalidApiKey);
    }

    public function testItSuccessfullyInvokesTheModel()
    {
        $expectedResponse = [
            'model' => 'llama-3.3-70b',
            'input' => [
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello, world!'],
                ],
            ],
            'temperature' => 0.5,
        ];
        $httpClient = new MockHttpClient(
            new JsonMockResponse($expectedResponse),
        );

        $platform = Factory::createPlatform('csk-1234567890abcdef', $httpClient);

        $result = $platform->invoke('llama-3.3-70b', new MessageBag(Message::ofUser('Hello, world!')))->getRawResult();
        $data = $result->getData();
        $info = $result->getObject()->getInfo();

        $this->assertNotEmpty($data);
        $this->assertNotEmpty($info);
        $this->assertSame('POST', $info['http_method']);
        $this->assertSame('https://api.cerebras.ai/v1/chat/completions', $info['url']);
        $this->assertSame($expectedResponse, $data);
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame('https://cerebras.example.com/v1/chat/completions', $url);

            return new JsonMockResponse(['choices' => []]);
        });

        $platform = Factory::createPlatform('csk-1234567890abcdef', $httpClient, baseUrl: 'https://cerebras.example.com/');
        $platform->invoke('llama-3.3-70b', new MessageBag(Message::ofUser('Hello')));
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new JsonMockResponse(['choices' => []]);
        });

        $platform = Factory::createPlatform('csk-1234567890abcdef', $httpClient);
        $platform->invoke('llama-3.3-70b', new MessageBag(Message::ofUser("tool output \xB1 here")));
    }
}
