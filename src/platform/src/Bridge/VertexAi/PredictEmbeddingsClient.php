<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\TaskType;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\VertexAi\Transport\VertexAiTransport;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model as BaseModel;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class PredictEmbeddingsClient implements ApiClientInterface
{
    public function __construct(
        private readonly VertexAiTransport $transport,
    ) {
    }

    public function supports(BaseModel $model): bool
    {
        return $model->supports(Capability::EMBEDDINGS);
    }

    public function request(BaseModel $model, array|string $payload, array $options = []): RawResultInterface
    {
        $modelOptions = $model->getOptions();

        $payload = [
            'instances' => array_map(
                static fn (string $text) => [
                    'content' => $text,
                    'title' => $options['title'] ?? null,
                    'task_type' => $modelOptions['task_type'] ?? TaskType::RETRIEVAL_QUERY,
                ],
                \is_array($payload) ? $payload : [$payload],
            ),
        ];

        unset($modelOptions['task_type']);

        return $this->transport->send(\sprintf('models/%s:%s', $model->getName(), 'predict'), array_merge($payload, $modelOptions));
    }

    public function convert(RawResultInterface $result, array $options = []): VectorResult
    {
        $this->transport->throwOnError($result, $options);

        $data = $result->getData();

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error from Embeddings API: "%s"', $data['error']['message'] ?? 'Unknown error'), $data['error']['code']);
        }

        if (!isset($data['predictions'])) {
            throw new RuntimeException('Response does not contain data.');
        }

        return new VectorResult(
            array_map(
                static fn (array $item): Vector => new Vector($item['embeddings']['values']),
                $data['predictions'],
            ),
        );
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }
}
