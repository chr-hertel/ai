<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\Generic\Embeddings\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * This generic implementation is based on OpenAI's initial embeddings endpoint, that got later adopted by other
 * providers as well. It can be used by any bridge or directly with the generic Factory.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
class EmbeddingsClient implements ApiClientInterface
{
    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(
        private readonly HttpTransport $transport,
        private readonly string $path = '/v1/embeddings',
        private readonly string $modelClass = EmbeddingsModel::class,
        private readonly TokenUsageExtractorInterface $tokenUsageExtractor = new TokenUsageExtractor(),
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof $this->modelClass;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return $this->transport->send($this->path, array_merge($options, [
            'model' => $model->getName(),
            'input' => $payload,
        ]));
    }

    public function convert(RawResultInterface $raw, array $options = []): VectorResult
    {
        $this->transport->throwOnError($raw, $options);

        $data = $raw->getData();

        if (!isset($data['data']) || ([] !== $data['data'] && !isset($data['data'][0]['embedding']))) {
            throw new RuntimeException('Response does not contain data.');
        }

        return new VectorResult(
            array_map(
                static fn (array $item): Vector => new Vector($item['embedding']),
                $data['data'],
            ),
        );
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return $this->tokenUsageExtractor;
    }
}
