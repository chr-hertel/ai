<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate;

use Symfony\AI\Platform\ApiClientInterface;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MetaPredictionsClient implements ApiClientInterface
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Llama;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!$model instanceof Llama) {
            throw new InvalidArgumentException(\sprintf('The model must be an instance of "%s".', Llama::class));
        }

        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        return new RawHttpResult(
            $this->client->request(\sprintf('meta/meta-%s', $model->getName()), 'predictions', $payload)
        );
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        if ($result instanceof RawHttpResult && 200 !== $result->getObject()->getStatusCode()) {
            $data = $result->getData();
            throw new RuntimeException(\sprintf('Replicate API error (HTTP %d): "%s".', $result->getObject()->getStatusCode(), $data['detail'] ?? $result->getObject()->getContent(false)));
        }

        $data = $result->getData();

        if (!isset($data['output'])) {
            throw new RuntimeException('Response does not contain output.');
        }

        return new TextResult(implode('', $data['output']));
    }

    public function getTokenUsageExtractor(): null
    {
        return null;
    }
}
