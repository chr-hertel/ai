<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Base for the Venice API clients: they all talk to the same scoped HTTP client, which carries the
 * base URI and the API key, so a client only names its path and its body.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractVeniceClient implements ApiClientInterface
{
    use VeniceErrorHandlingTrait;

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function postJson(string $path, array $payload): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $path, ['json' => $payload]));
    }

    protected function getCheckedResponse(RawResultInterface $result): ResponseInterface
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        $this->throwOnHttpError($response);

        return $response;
    }
}
