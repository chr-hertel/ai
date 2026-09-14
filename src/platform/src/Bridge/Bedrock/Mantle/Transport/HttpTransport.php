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
use Symfony\AI\Platform\Bridge\Bedrock\Mantle\SigV4RequestSigner;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport as GenericHttpTransport;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to the OpenAI-compatible routes of the AWS Bedrock Mantle endpoint.
 *
 * Requests are authenticated either with a Bedrock API key sent as a bearer token (recommended) or
 * with AWS SigV4 signing using the standard credential chain. When an API key is provided it takes
 * precedence over SigV4.
 *
 * @author asrar <aszenz@gmail.com>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport extends GenericHttpTransport
{
    use JsonBodyEncodingTrait;

    private readonly ?SigV4RequestSigner $requestSigner;

    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
        string $region,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?CredentialProvider $credentialProvider = null,
    ) {
        parent::__construct($httpClient, $baseUrl, $apiKey);

        $this->requestSigner = null === $apiKey
            ? new SigV4RequestSigner($region, $credentialProvider ?? ChainProvider::createDefaultChain($httpClient))
            : null;
    }

    public function send(string $path, array $payload): RawResultInterface
    {
        $body = $this->encodeJsonBody($payload);
        $url = $this->baseUrl.$path;

        if (null !== $this->apiKey) {
            return new RawHttpResult($this->httpClient->request('POST', $url, [
                'auth_bearer' => $this->apiKey,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $body,
            ]));
        }

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => $this->requestSigner?->sign($url, $path, $body),
            'body' => $body,
        ]));
    }
}
