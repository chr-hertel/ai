<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\Gemini\Transport\TransportInterface;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Valtteri R <valtzu@gmail.com>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BatchEmbedContentsClient implements ApiClientInterface
{
    public function __construct(
        private readonly TransportInterface $transport,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model->supports(Capability::EMBEDDINGS);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $modelOptions = $model->getOptions();

        return $this->transport->send(\sprintf('models/%s:%s', $model->getName(), 'batchEmbedContents'), [
            'requests' => array_map(
                static fn (string $text) => array_filter([
                    'model' => 'models/'.$model->getName(),
                    'content' => ['parts' => [['text' => $text]]],
                    'outputDimensionality' => $modelOptions['dimensions'] ?? null,
                    'taskType' => $modelOptions['task_type'] ?? null,
                    'title' => $options['title'] ?? null,
                ]),
                \is_array($payload) ? $payload : [$payload],
            ),
        ]);
    }

    public function convert(RawResultInterface $result, array $options = []): VectorResult
    {
        $this->transport->throwOnError($result, $options);

        $data = $result->getData();

        if (!isset($data['embeddings'])) {
            throw new RuntimeException('Response does not contain data.');
        }

        return new VectorResult(
            array_map(
                static fn (array $item): Vector => new Vector($item['values']),
                $data['embeddings'],
            ),
        );
    }

    public function getTokenUsageExtractor(): null
    {
        return null;
    }
}
