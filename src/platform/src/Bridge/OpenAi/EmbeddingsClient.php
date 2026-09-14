<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\OpenAi\Transport\TransportInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbeddingsClient implements ApiClientInterface
{
    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $modelClass = Embeddings::class,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof $this->modelClass;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return $this->transport->send('/v1/embeddings', array_merge($options, [
            'model' => $model->getName(),
            'input' => $payload,
        ]));
    }

    public function convert(RawResultInterface $result, array $options = []): VectorResult
    {
        $this->transport->throwOnError($result, $options);

        $data = $result->getData();

        if (!isset($data['data'])) {
            if ($result instanceof RawHttpResult) {
                throw new RuntimeException(\sprintf('Response from OpenAI API does not contain "data" key. StatusCode: "%s". Response: "%s".', $result->getObject()->getStatusCode(), json_encode($result->getData(), \JSON_THROW_ON_ERROR)));
            }

            throw new RuntimeException('Response does not contain data.');
        }

        return new VectorResult(
            array_map(
                static fn (array $item): Vector => new Vector($item['embedding']),
                $data['data']
            ),
        );
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }
}
