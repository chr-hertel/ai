<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Transport;

use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class HttpTransport
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    private readonly EventSourceHttpClient $httpClient;

    /**
     * @param array<string, string> $extraHeaders
     */
    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        private readonly array $extraHeaders = [],
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function send(string $path, array $payload): RawResultInterface
    {
        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->extraHeaders,
        );

        $httpOptions = [
            'headers' => $headers,
            'body' => $this->encodeJsonBody($payload),
        ];

        if (null !== $this->apiKey) {
            $httpOptions['auth_bearer'] = $this->apiKey;
        }

        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').$path, $httpOptions);

        return new RawHttpResult($response);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function throwOnError(RawResultInterface $result, array $options = []): void
    {
        if (!$result instanceof RawHttpResult) {
            return;
        }

        $response = $result->getObject();

        if (400 === $response->getStatusCode()) {
            $this->throwOnContextOverflow($response);
        }

        $this->throwOnHttpError($response);
    }

    protected function throwOnContextOverflow(ResponseInterface $response): void
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return;
        }

        $code = $data['error']['code'] ?? $data['code'] ?? null;
        $message = $data['error']['message'] ?? $data['message'] ?? '';

        if ('context_length_exceeded' === $code || str_contains($message, 'context length') || preg_match('/context[_ ]length[_ ]exceeded/i', $message)) {
            throw new ExceedContextSizeException('' !== $message ? $message : 'Context size exceeded');
        }
    }
}
