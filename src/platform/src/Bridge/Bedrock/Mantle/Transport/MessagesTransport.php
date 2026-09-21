<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Mantle\Transport;

use AsyncAws\Core\Credentials\ChainProvider;
use AsyncAws\Core\Credentials\CredentialProvider;
use Symfony\AI\Platform\Bridge\Anthropic\Transport\TransportInterface;
use Symfony\AI\Platform\Bridge\Bedrock\Mantle\SigV4RequestSigner;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Serves the Anthropic Messages API on its own route of the AWS Bedrock Mantle endpoint, which is
 * reached on a single path and authenticates with a Bedrock API key or AWS SigV4 signing.
 *
 * @author asrar <aszenz@gmail.com>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessagesTransport implements TransportInterface
{
    use JsonBodyEncodingTrait;

    private const PATH = '/anthropic/v1/messages';

    private readonly EventSourceHttpClient $httpClient;
    private readonly ?SigV4RequestSigner $requestSigner;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        string $region,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        ?CredentialProvider $credentialProvider = null,
        private readonly ?string $workspace = null,
    ) {
        $this->requestSigner = null === $apiKey
            ? new SigV4RequestSigner($region, $credentialProvider ?? ChainProvider::createDefaultChain($httpClient))
            : null;

        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function send(Model $model, string $path, array $payload, array $headers = []): RawResultInterface
    {
        if (isset($payload['output_config']['format'])) {
            throw new InvalidArgumentException('Structured outputs are not supported by the Anthropic Messages API on the Bedrock Mantle endpoint.');
        }

        // Mantle serves the Messages API over plain HTTP, so the model travels in the body.
        $payload = array_merge(['model' => $model->getName()], $payload);

        $headers = array_merge([
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ], $headers);

        if (null !== $this->workspace) {
            $headers['anthropic-workspace'] = $this->workspace;
        }

        $body = $this->encodeJsonBody($payload);
        $url = $this->baseUrl.self::PATH;

        if (null !== $this->apiKey) {
            $headers['x-api-key'] = $this->apiKey;
        } else {
            $headers = $this->requestSigner?->sign($url, self::PATH, $body, $headers) ?? $headers;
        }

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'body' => $body,
        ]));
    }

    public function throwOnError(RawResultInterface $result, array $options = []): void
    {
    }
}
