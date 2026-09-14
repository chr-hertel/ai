<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Mantle;

use AsyncAws\Core\Credentials\ChainProvider;
use AsyncAws\Core\Credentials\CredentialProvider;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesClient as OpenResponsesClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Serves the OpenAI Responses API on its own route of the AWS Bedrock Mantle endpoint, which
 * authenticates with a Bedrock API key sent as a bearer token or with AWS SigV4 signing.
 *
 * @author asrar <aszenz@gmail.com>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResponsesClient extends OpenResponsesClient
{
    private readonly ?SigV4RequestSigner $requestSigner;

    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
        string $region,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?CredentialProvider $credentialProvider = null,
        string $path = '/openai/v1/responses',
    ) {
        parent::__construct($httpClient, $baseUrl, $apiKey, $path);

        $this->requestSigner = null === $apiKey
            ? new SigV4RequestSigner($region, $credentialProvider ?? ChainProvider::createDefaultChain($httpClient))
            : null;
    }

    protected function createRequestOptions(string $body): array
    {
        if (null !== $this->requestSigner) {
            return [
                'headers' => $this->requestSigner->sign($this->baseUrl.$this->path, $this->path, $body),
                'body' => $body,
            ];
        }

        return parent::createRequestOptions($body);
    }
}
