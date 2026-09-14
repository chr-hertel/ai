<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\Decision\AnswersConverter;
use Symfony\AI\Platform\Bridge\Decision\RequestBodyBuilder;
use Symfony\AI\Platform\Bridge\Decision\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class SystemOneClient implements ApiClientInterface
{
    use HttpStatusErrorHandlingTrait;

    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a TypeSafe-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.typesafe.ai',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Jev;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/systemone', [
            'auth_bearer' => $this->apiKey,
            'json' => RequestBodyBuilder::build($model, $payload),
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): ObjectResult
    {
        $httpResponse = $result->getObject();
        \assert($httpResponse instanceof ResponseInterface);

        if (422 === $httpResponse->getStatusCode()) {
            throw new BadRequestException($this->extractErrorMessage($httpResponse) ?? 'Unprocessable Entity');
        }

        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $httpResponse->getContent(false)));
        }

        return new ObjectResult((new AnswersConverter())->convert($result->getData()));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    /**
     * TypeSafe nests its errors under a "detail" key, which the default implementation of the trait does not read:
     * a message for API errors, and a list of violations for payloads failing validation.
     */
    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        $detail = $data['detail'] ?? null;

        if (\is_string($detail)) {
            return $detail;
        }

        if (!\is_array($detail)) {
            return null;
        }

        if (\is_string($detail['message'] ?? null)) {
            return $detail['message'];
        }

        $violations = [];
        foreach ($detail as $violation) {
            if (\is_array($violation) && isset($violation['loc'], $violation['msg']) && \is_array($violation['loc'])) {
                $violations[] = \sprintf('%s: %s', implode('.', $violation['loc']), $violation['msg']);
            }
        }

        return [] !== $violations ? implode('; ', $violations) : null;
    }
}
