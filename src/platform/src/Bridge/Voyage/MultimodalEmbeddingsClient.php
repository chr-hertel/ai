<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MultimodalEmbeddingsClient implements ApiClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Voyage-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.voyageai.com',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Voyage && $model->supports(Capability::INPUT_MULTIMODAL);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $body = [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'model' => $model->getName(),
                'inputs' => $payload,
                'input_type' => $options['input_type'] ?? null,
                'truncation' => $options['truncation'] ?? true,
                'output_encoding' => $options['encoding'] ?? null,
            ],
        ];

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/v1/multimodalembeddings', $this->baseUrl), $body));
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $result = $result->getData();

        if (!isset($result['data'])) {
            throw new RuntimeException('Response does not contain embedding data.');
        }

        return new VectorResult(
            array_map(
                static fn (array $data) => new Vector($data['embedding']),
                $result['data'],
            ),
        );
    }

    public function getTokenUsageExtractor(): null
    {
        return null;
    }
}
