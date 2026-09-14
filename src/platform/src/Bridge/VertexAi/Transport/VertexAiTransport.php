<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Transport;

use Symfony\AI\Platform\Bridge\Gemini\Transport\TransportInterface;
use Symfony\AI\Platform\Bridge\VertexAi\RegionAwareTrait;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VertexAiTransport implements TransportInterface
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;
    use RegionAwareTrait;

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;

    public function __construct(
        HttpClientInterface $httpClient,
        ?string $location = null,
        ?string $projectId = null,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = self::getBaseUrl($location, $projectId);
    }

    public function send(string $path, array $payload): RawResultInterface
    {
        $query = [];
        if (null !== $this->apiKey) {
            $query['key'] = $this->apiKey;
        }

        $response = $this->httpClient->request('POST', $this->baseUrl.ltrim($path, '/'), [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->encodeJsonBody($payload),
            'query' => $query,
        ]);

        return new RawHttpResult($response);
    }

    public function throwOnError(RawResultInterface $result, array $options = []): void
    {
        if (!$result instanceof RawHttpResult) {
            return;
        }

        $response = $result->getObject();

        if (400 === $response->getStatusCode()) {
            $message = $this->extractErrorMessage($response) ?? '';

            if (str_contains($message, 'maximum number of tokens') || str_contains($message, 'input token count')) {
                throw new ExceedContextSizeException($message);
            }
        }

        $this->throwOnHttpError($response);
    }
}
